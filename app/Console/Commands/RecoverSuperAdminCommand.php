<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoverSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:recover-super-admin 
                            {email : The email address of the Super Admin account} 
                            {--reason= : Mandatory reason for emergency recovery audit trail} 
                            {--force : Force operation without interactive prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Secure out-of-band disaster recovery procedure for locked-out or compromised Super Admin accounts.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Error: User with email '{$email}' not found.");
            return self::FAILURE;
        }

        if (!$user->isTechnicalAuthority()) {
            $this->error("Security Violation: User '{$email}' is not a designated Protected Technical Authority (Super Admin). This command is restricted to Technical Authorities.");
            return self::FAILURE;
        }

        $reason = $this->option('reason');
        if (empty($reason)) {
            if ($this->option('force')) {
                $reason = 'Automated emergency recovery via CLI with --force flag';
            } else {
                $reason = $this->ask('Enter mandatory reason for emergency recovery (e.g., lost MFA device, credential rotation)');
            }
        }

        if (empty($reason)) {
            $this->error('Error: A valid reason is strictly required for audit trail compliance.');
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("Are you sure you want to execute emergency recovery for '{$user->name}' ({$email})? All active sessions will be terminated.")) {
            $this->info('Recovery aborted.');
            return self::SUCCESS;
        }

        // Generate high-entropy temporary password
        $tempPassword = 'Rec!' . Str::random(12) . '9#';

        $user->password = Hash::make($tempPassword);
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->status = UserStatus::ACTIVE;
        $user->save();

        // Immediately terminate all existing active sessions
        $terminatedSessions = DB::table('sessions')->where('user_id', $user->id)->delete();

        // Record immutable audit log entry
        AuditLog::log('super_admin_emergency_recovery', $user, null, [
            'reason' => $reason,
            'sessions_cleared' => $terminatedSessions,
            'executed_via' => 'CLI_COMMAND',
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->newLine();
        $this->info('================================================================');
        $this->info('✅ SUPER ADMIN EMERGENCY RECOVERY COMPLETED SUCCESSFULLY');
        $this->info('================================================================');
        $this->line("Target Account:      <fg=cyan>{$user->name}</> ({$user->email})");
        $this->line("Account Status:      <fg=green>Active</>");
        $this->line("2FA Status:          <fg=yellow>Reset (Must be reconfigured upon login)</>");
        $this->line("Sessions Purged:     <fg=cyan>{$terminatedSessions}</>");
        $this->line("Audit Log ID:        <fg=green>Logged to audit trail</>");
        $this->newLine();
        $this->warn("Temporary Password:  {$tempPassword}");
        $this->warn('CRITICAL: Promptly log in at /login, change this password immediately, and re-enroll Two-Factor Authentication.');
        $this->info('================================================================');

        return self::SUCCESS;
    }
}

