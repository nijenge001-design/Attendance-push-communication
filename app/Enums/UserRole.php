<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Operator = 'operator';
    case Viewer = 'viewer';
    case Integration = 'integration';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Operator => 'Operator',
            self::Viewer => 'Viewer',
            self::Integration => 'Integration',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->label()])
            ->all();
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isManager(): bool
    {
        return $this === self::Admin || $this === self::Manager;
    }

    public function isOperator(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::Operator], true);
    }

    public function isViewer(): bool
    {
        return $this === self::Viewer;
    }

    public function isIntegration(): bool
    {
        return $this === self::Integration;
    }
}
