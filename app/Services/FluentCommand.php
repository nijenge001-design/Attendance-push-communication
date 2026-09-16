<?php

namespace App\Services;

use Random\RandomException;

class FluentCommand
{
    private string $commandType = '';
    private array $fields = [];

    public static function make(): self
    {
        return new self();
    }

    public function command(string $type): self
    {
        $this->commandType = trim($type);
        return $this;
    }

    public function set(string $key, mixed $value): self
    {
        if ($value !== null && $value !== '') {
            // Escape tabs and newlines (protocol uses \t and \n as delimiters)
            $escaped = str_replace(["\t", "\n", "\r"], ['\t', '\n', '\r'], (string)$value);
//            $this->fields[$key] = $value;
            $this->fields[$key] = $escaped;
        }
        return $this;
    }

    public function setMany(array $fields): self
    {
        foreach ($fields as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function build(): string
    {
        if ($this->commandType === '') {
            throw new \RuntimeException('Command type has not been set.');
        }

        if (empty($this->fields)) {
            return $this->commandType;
        }

        $parts = [];
        foreach ($this->fields as $key => $value) {
            $parts[] = "{$key}={$value}";
        }

        return $this->commandType . ' ' . implode("\t", $parts);
    }

    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        return CommandBuilder::generateCommand($this->build());
    }

    public static function extractCmdId(string $fullCommand): ?string
    {
        return CommandBuilder::extractCmdId($fullCommand);
    }

    // Convenience setters
    public function pin(string $pin): self
    {
        return $this->set('PIN', $pin);
    }

    public function name(string $name): self
    {
        return $this->set('Name', $name);
    }

    public function pri(int $pri): self
    {
        return $this->set('Pri', $pri);
    }

    public function passwd(string $passwd): self
    {
        return $this->set('Passwd', $passwd);
    }

    public function card(string $card): self
    {
        return $this->set('Card', $card);
    }

    public function grp(string|int $grp): self
    {
        return $this->set('Grp', $grp);
    }

    public function tz(string $tz): self
    {
        return $this->set('TZ', $tz);
    }

    public function verify(int $verify): self
    {
        return $this->set('Verify', $verify);
    }

    public function fid(int $fid): self
    {
        return $this->set('FID', $fid);
    }

    public function tmp(string $base64): self
    {
        return $this->set('TMP', $base64);
    }

    public function size(int $size): self
    {
        return $this->set('Size', $size);
    }

    public function valid(int $valid): self
    {
        return $this->set('Valid', $valid);
    }

    public function type(int $type): self
    {
        return $this->set('Type', $type);
    }

    public function no(int $no): self
    {
        return $this->set('No', $no);
    }

    public function index(int $index): self
    {
        return $this->set('Index', $index);
    }

    public function startTime(string $time): self
    {
        return $this->set('StartTime', $time);
    }

    public function endTime(string $time): self
    {
        return $this->set('EndTime', $time);
    }

    public function retry(int $retry): self
    {
        return $this->set('RETRY', $retry);
    }

    public function overwrite(int $overwrite): self
    {
        return $this->set('OVERWRITE', $overwrite);
    }

    public function content(string $base64): self
    {
        return $this->set('Content', $base64);
    }

    public function cardNo(string $cardNo): self
    {
        return $this->set('CardNo', $cardNo);
    }

    // Factories
    public static function updateUser(): self
    {
        return self::make()->command('DATA UPDATE USERINFO');
    }

    public static function updateFingerprint(): self
    {
        return self::make()->command('DATA UPDATE FINGERTMP');
    }

    public static function updateFace(): self
    {
        return self::make()->command('DATA UPDATE FACE');
    }

    public static function updateBioData(): self
    {
        return self::make()->command('DATA UPDATE BIODATA');
    }

    public static function updateUserPhoto(): self
    {
        return self::make()->command('DATA UPDATE USERPIC');
    }

    public static function queryAttLog(): self
    {
        return self::make()->command('DATA QUERY ATTLOG');
    }

    public static function queryUserInfo(): self
    {
        return self::make()->command('DATA QUERY USERINFO');
    }

    public static function deleteUser(): self
    {
        return self::make()->command('DATA DELETE USERINFO');
    }

    public static function enrollFp(): self
    {
        return self::make()->command('ENROLL_FP');
    }

    public static function enrollBio(): self
    {
        return self::make()->command('ENROLL_BIO');
    }

    public static function reboot(): self
    {
        return self::make()->command('REBOOT');
    }

    public static function unlockDoor(): self
    {
        return self::make()->command('AC_UNLOCK');
    }
}
