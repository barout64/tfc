<?php

namespace App\Enums;

enum Role: string
{
    use HasOptions;

    case Admin = 'admin';
    case Management = 'management';
    case LogisticsOfficer = 'logistics_officer';
    case WarehouseOfficer = 'warehouse_officer';
    case SalesOfficer = 'sales_officer';
    case FinanceOfficer = 'finance_officer';
    case ExtensionOfficer = 'extension_officer';
    case AgroDealer = 'agro_dealer';
    case Transporter = 'transporter';
    case Viewer = 'viewer';

    /**
     * Abilities granted to each role. "*" means everything.
     *
     * @return list<string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => ['*'],
            self::Management => ['dashboard', 'stock.view', 'logistics.view', 'sales.view', 'farmers.view', 'dealers.view', 'reports', 'complaints.manage', 'approve', 'finance.view', 'planning.manage'],
            self::LogisticsOfficer => ['dashboard', 'stock.view', 'logistics.view', 'logistics.manage', 'warehouses.manage', 'reports', 'stock.receive'],
            self::WarehouseOfficer => ['dashboard', 'stock.view', 'stock.manage', 'stock.receive', 'logistics.view', 'sales.view', 'sales.manage', 'farmers.view'],
            self::SalesOfficer => ['dashboard', 'stock.view', 'sales.view', 'sales.manage', 'farmers.view', 'farmers.manage', 'dealers.view', 'complaints.manage', 'reports'],
            self::FinanceOfficer => ['dashboard', 'sales.view', 'finance.view', 'finance.manage', 'logistics.view', 'reports'],
            self::ExtensionOfficer => ['dashboard', 'farmers.view', 'farmers.manage', 'advisories.manage', 'complaints.manage', 'stock.view'],
            self::AgroDealer => ['dealer.portal'],
            self::Transporter => ['transporter.portal'],
            self::Viewer => ['dashboard', 'stock.view', 'logistics.view', 'sales.view', 'farmers.view', 'reports'],
        };
    }

    public function can(string $ability): bool
    {
        $abilities = $this->abilities();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function isStaff(): bool
    {
        return ! in_array($this, [self::AgroDealer, self::Transporter], true);
    }
}
