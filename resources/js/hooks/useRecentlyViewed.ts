import { useState, useEffect, useRef, useCallback } from 'react';
import axios from 'axios';

export interface ViewedItem {
    product_id: number;
    viewed_at: string; // ISO 8601 UTC Zulu with whole seconds: "2026-11-14T07:18:00Z"
}

export interface MergeSnapshot {
    key: string;        // UUID v4
    userId: number;     // Owning customer ID
    items: ViewedItem[];
    attempts: number;
}

export interface TabSyncEvent {
    type: 'AUTH_LOGOUT' | 'MERGE_COMPLETE';
    userId: number;
    ts: number;
}

const STORAGE_GUEST_KEY   = 'aura_recently_viewed';
const STORAGE_PENDING_KEY = 'aura_pending_merge';
const STORAGE_SYNC_KEY    = 'aura_auth_sync_event';
const CHANNEL_NAME        = 'aura_rvp_sync';

export function isValidSyncEvent(data: unknown): data is TabSyncEvent {
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        return false;
    }

    const event = data as Record<string, unknown>;

    // 1. Supported event type
    if (event.type !== 'AUTH_LOGOUT' && event.type !== 'MERGE_COMPLETE') {
        return false;
    }

    // 2. Positive integer userId
    if (
        typeof event.userId !== 'number' ||
        !Number.isInteger(event.userId) ||
        event.userId <= 0
    ) {
        return false;
    }

    // 3. Valid finite numeric timestamp
    if (typeof event.ts !== 'number' || !Number.isFinite(event.ts)) {
        return false;
    }

    const now = Date.now();

    // 4. Maximum event age: 10,000 ms (10 seconds)
    if (now - event.ts > 10_000) {
        return false;
    }

    // 5. Future clock skew tolerance: max 2,000 ms (2 seconds into future)
    if (event.ts - now > 2_000) {
        return false;
    }

    return true;
}

export function useRecentlyViewed(currentUser?: { id: number } | null) {
    const [items, setItems] = useState<ViewedItem[]>([]);
    const [isMerging, setIsMerging] = useState(false);
    const activeUserIdRef   = useRef<number | null>(currentUser?.id ?? null);
    const abortCtrlRef      = useRef<AbortController | null>(null);
    const retryTimerRef     = useRef<number | null>(null);
    const channelRef        = useRef<BroadcastChannel | null>(null);

    // Keep active user ref updated
    useEffect(() => {
        activeUserIdRef.current = currentUser?.id ?? null;
    }, [currentUser?.id]);

    const safeStorageGet = (key: string): string | null => {
        try {
            return localStorage.getItem(key);
        } catch {
            return null;
        }
    };

    const safeStorageSet = (key: string, value: string): void => {
        try {
            localStorage.setItem(key, value);
        } catch {
            // Quota exceeded or private browsing block
        }
    };

    const safeStorageRemove = (key: string): void => {
        try {
            localStorage.removeItem(key);
        } catch {
            // Ignore access errors
        }
    };

    const broadcastEvent = (event: TabSyncEvent): void => {
        if (channelRef.current) {
            try {
                channelRef.current.postMessage(event);
            } catch {
                // Channel detached
            }
        }
        safeStorageSet(STORAGE_SYNC_KEY, JSON.stringify(event));
        Promise.resolve().then(() => safeStorageRemove(STORAGE_SYNC_KEY));
    };

    const performLocalCleanup = useCallback(() => {
        if (abortCtrlRef.current) {
            abortCtrlRef.current.abort();
            abortCtrlRef.current = null;
        }
        if (retryTimerRef.current !== null) {
            clearTimeout(retryTimerRef.current);
            retryTimerRef.current = null;
        }
        safeStorageRemove(STORAGE_GUEST_KEY);
        safeStorageRemove(STORAGE_PENDING_KEY);
        setItems([]);
        setIsMerging(false);
        activeUserIdRef.current = null;
    }, []);

    // Record a view locally (guest browsing)
    const recordGuestView = useCallback((productId: number) => {
        let current: ViewedItem[] = [];
        const raw = safeStorageGet(STORAGE_GUEST_KEY);
        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    current = parsed;
                }
            } catch {
                current = [];
            }
        }

        // Format to whole-second UTC Zulu (strip fractional seconds)
        const now = new Date().toISOString().replace(/\.\d{3}Z$/, 'Z');
        const filtered = current.filter((i) => i.product_id !== productId);
        const updated: ViewedItem[] = [{ product_id: productId, viewed_at: now }, ...filtered].slice(0, 15);

        safeStorageSet(STORAGE_GUEST_KEY, JSON.stringify(updated));
        setItems(updated);
    }, []);

    // Dispatch merge to backend
    const dispatchMerge = useCallback(async (snapshot: MergeSnapshot) => {
        if (abortCtrlRef.current) {
            abortCtrlRef.current.abort();
        }
        abortCtrlRef.current = new AbortController();

        setIsMerging(true);
        try {
            const response = await axios.post(
                '/customer/recently-viewed/merge',
                {
                    idempotency_key: snapshot.key,
                    items: snapshot.items,
                },
                {
                    signal: abortCtrlRef.current.signal,
                }
            );

            // Stale check
            if (activeUserIdRef.current !== snapshot.userId) {
                return;
            }

            if (response.status === 200) {
                safeStorageRemove(STORAGE_PENDING_KEY);
                setIsMerging(false);

                broadcastEvent({
                    type: 'MERGE_COMPLETE',
                    userId: snapshot.userId,
                    ts: Date.now(),
                });
            }
        } catch (error: any) {
            if (axios.isCancel(error)) {
                return;
            }

            const status = error.response?.status;
            if (status === 409 || status === 422) {
                // Terminal error: drop snapshot, do not retry
                safeStorageRemove(STORAGE_PENDING_KEY);
                setIsMerging(false);
            } else {
                // Transient error: increment attempts and schedule retry
                const nextAttempts = snapshot.attempts + 1;
                const updated: MergeSnapshot = { ...snapshot, attempts: nextAttempts };
                safeStorageSet(STORAGE_PENDING_KEY, JSON.stringify(updated));
                setIsMerging(false);

                if (nextAttempts < 3 && activeUserIdRef.current === snapshot.userId) {
                    const delay = Math.pow(2, nextAttempts) * 1000;
                    retryTimerRef.current = window.setTimeout(() => {
                        if (activeUserIdRef.current === snapshot.userId) {
                            dispatchMerge(updated);
                        }
                    }, delay);
                }
            }
        }
    }, []);

    // Cross-tab synchronization setup
    useEffect(() => {
        const hasBC = typeof window !== 'undefined' && 'BroadcastChannel' in window;
        if (hasBC) {
            try {
                channelRef.current = new BroadcastChannel(CHANNEL_NAME);
                channelRef.current.onmessage = (e: MessageEvent) => {
                    if (isValidSyncEvent(e.data)) {
                        handleSyncEvent(e.data);
                    }
                };
            } catch {
                channelRef.current = null;
            }
        }

        const handleStorageEvent = (e: StorageEvent) => {
            if (e.key === STORAGE_SYNC_KEY && e.newValue) {
                try {
                    const parsed = JSON.parse(e.newValue);
                    if (isValidSyncEvent(parsed)) {
                        handleSyncEvent(parsed);
                    }
                } catch {
                    // Ignore parse error
                }
            }
        };

        window.addEventListener('storage', handleStorageEvent);

        return () => {
            if (channelRef.current) {
                try {
                    channelRef.current.close();
                } catch {
                    // Ignore
                }
                channelRef.current = null;
            }
            window.removeEventListener('storage', handleStorageEvent);
        };
    }, [performLocalCleanup]);

    const handleSyncEvent = (event: TabSyncEvent) => {
        if (event.type === 'AUTH_LOGOUT') {
            if (activeUserIdRef.current === event.userId || activeUserIdRef.current === null) {
                performLocalCleanup();
            }
        } else if (event.type === 'MERGE_COMPLETE') {
            if (activeUserIdRef.current === event.userId) {
                safeStorageRemove(STORAGE_PENDING_KEY);
                setIsMerging(false);
            }
        }
    };

    // Account switching & lifecycle isolation
    const prevUserRef = useRef<{ id: number } | null>(currentUser ?? null);
    useEffect(() => {
        const prevUser = prevUserRef.current;
        const currUser = currentUser ?? null;

        // Case 1: Logout occurred (was user, now null)
        if (prevUser !== null && currUser === null) {
            const loggedOutId = prevUser.id;
            performLocalCleanup();
            broadcastEvent({ type: 'AUTH_LOGOUT', userId: loggedOutId, ts: Date.now() });
        }

        // Unauthenticated session guard: ensure no stale pending merge snapshot lingers
        if (currUser === null) {
            safeStorageRemove(STORAGE_PENDING_KEY);
        }

        // Case 2: User authenticated (login or account switch)
        if (currUser !== null) {
            if (prevUser !== null && prevUser.id !== currUser.id) {
                // Switched from a different user — purge old state immediately
                performLocalCleanup();
            }

            activeUserIdRef.current = currUser.id;

            // Check if existing snapshot belongs to another user
            const pendingRaw = safeStorageGet(STORAGE_PENDING_KEY);
            if (pendingRaw) {
                try {
                    const snapshot: MergeSnapshot = JSON.parse(pendingRaw);
                    if (snapshot.userId !== currUser.id) {
                        safeStorageRemove(STORAGE_PENDING_KEY);
                    } else {
                        // Resume existing snapshot
                        dispatchMerge(snapshot);
                    }
                } catch {
                    safeStorageRemove(STORAGE_PENDING_KEY);
                }
            } else {
                // If there are guest items accumulated, snapshot and merge them
                const guestRaw = safeStorageGet(STORAGE_GUEST_KEY);
                if (guestRaw) {
                    try {
                        const guestItems: ViewedItem[] = JSON.parse(guestRaw);
                        if (Array.isArray(guestItems) && guestItems.length > 0) {
                            const newSnapshot: MergeSnapshot = {
                                key: crypto.randomUUID(),
                                userId: currUser.id,
                                items: guestItems,
                                attempts: 0,
                            };
                            safeStorageSet(STORAGE_PENDING_KEY, JSON.stringify(newSnapshot));
                            safeStorageRemove(STORAGE_GUEST_KEY);
                            dispatchMerge(newSnapshot);
                        }
                    } catch {
                        safeStorageRemove(STORAGE_GUEST_KEY);
                    }
                }
            }
        }

        prevUserRef.current = currUser;
    }, [currentUser, performLocalCleanup, dispatchMerge]);

    return {
        items,
        isMerging,
        recordGuestView,
        performLocalCleanup,
    };
}

