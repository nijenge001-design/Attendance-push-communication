<?php

namespace App\Enums;

/**
 * Canonical permission names used across policies.
 * Role defaults live in RolePermissionSeeder; per-user overrides in user_permission.
 */
enum Permission: string
{
    case ManageDevices = 'manage-devices';
    case ApproveDevices = 'approve-devices';
    case ManageEmployees = 'manage-employees';
    case ReadEmployees = 'read-employees';
    case DeleteEmployees = 'delete-employees';
    case ManageSites = 'manage-sites';
    case ManageUsers = 'manage-users';
    case ManageCommands = 'manage-commands';
    case ResolveImportErrors = 'resolve-import-errors';
    case ReadAttendance = 'read-attendance';

    public function label(): string
    {
        return match ($this) {
            self::ManageDevices => 'Manage devices',
            self::ApproveDevices => 'Approve / block devices',
            self::ManageEmployees => 'Add / update employees',
            self::ReadEmployees => 'Read employees',
            self::DeleteEmployees => 'Delete employees',
            self::ManageSites => 'Manage sites',
            self::ManageUsers => 'Manage users',
            self::ManageCommands => 'Manage device commands',
            self::ResolveImportErrors => 'Resolve import errors',
            self::ReadAttendance => 'Read attendance',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Default permissions granted by each role (before user overrides).
     *
     * @return list<self>
     */
    public static function defaultsFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::Admin => self::cases(), // everything

            UserRole::Manager => [
                self::ManageDevices,
                self::ApproveDevices,
                self::ManageEmployees,
                self::ReadEmployees,
                self::DeleteEmployees,
                self::ManageSites,
                self::ResolveImportErrors,
                self::ReadAttendance,
                // no ManageUsers, no ManageCommands
            ],

            UserRole::Operator => [
                self::ManageDevices,
                self::ApproveDevices,
                self::ManageEmployees,
                self::ReadEmployees,
                self::ManageCommands,
                self::ResolveImportErrors,
                self::ReadAttendance,
            ],

            UserRole::Viewer => [
                self::ReadEmployees,
                self::ReadAttendance,
            ],

            UserRole::Integration => [
                self::ManageEmployees,
                self::ReadEmployees,
                self::ReadAttendance,
            ],
        };
    }
}
