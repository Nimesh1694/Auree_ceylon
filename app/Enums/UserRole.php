<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case SUPER_ADMIN = 'super_admin';
    case MARKETING_MANAGER = 'marketing_manager';
    case ACCOUNTANT = 'accountant';
    case PRODUCT_INVENTORY_MANAGER = 'product_inventory_manager';
    case OPERATIONS_MANAGER = 'operations_manager';
    case AGENT_MANAGER = 'agent_manager';
    case HIGH_LEVEL_AGENT = 'high_level_agent';
    case LOW_LEVEL_AGENT = 'low_level_agent';
    case CUSTOMER = 'customer';

    // Legacy backward-compatibility alias
    case IT_MANAGER = 'it_manager';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::SUPER_ADMIN => 'Super Admin',
            self::MARKETING_MANAGER => 'Marketing Manager',
            self::IT_MANAGER => 'IT Manager',
            self::ACCOUNTANT => 'Accountant',
            self::PRODUCT_INVENTORY_MANAGER => 'Product & Inventory Manager',
            self::OPERATIONS_MANAGER => 'Operations Manager',
            self::AGENT_MANAGER => 'Agent Manager',
            self::HIGH_LEVEL_AGENT => 'High-level Agent',
            self::LOW_LEVEL_AGENT => 'Low-level Agent',
            self::CUSTOMER => 'Customer',
        };
    }

    public function isManagement(): bool
    {
        return in_array($this, [
            self::OWNER,
            self::SUPER_ADMIN,
            self::MARKETING_MANAGER,
            self::IT_MANAGER,
            self::ACCOUNTANT,
            self::PRODUCT_INVENTORY_MANAGER,
            self::OPERATIONS_MANAGER,
            self::AGENT_MANAGER,
        ], true);
    }

    public function isAgent(): bool
    {
        return in_array($this, [
            self::HIGH_LEVEL_AGENT,
            self::LOW_LEVEL_AGENT,
        ], true);
    }

    public function isCustomer(): bool
    {
        return $this === self::CUSTOMER;
    }
}
