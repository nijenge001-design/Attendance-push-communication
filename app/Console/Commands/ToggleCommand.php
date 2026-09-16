<?php

namespace App\Console\Commands;

use App\Models\PendingCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('command:toggle {id : Command ID (cmd id) or primary key} {--enable : Enable the command} {--disable : Disable the command}')]
#[Description('Enable or disable a pending device command')]
class ToggleCommand extends Command
{
    public function handle(): int
    {
        $id = $this->argument('id');
        $enable = (bool) $this->option('enable');
        $disable = (bool) $this->option('disable');

        if ($enable === $disable) {
            $this->error('Specify exactly one of --enable or --disable.');

            return self::FAILURE;
        }

        $cmd = PendingCommand::query()
            ->where('command_id', $id)
            ->orWhere('id', $id)
            ->first();

        if (! $cmd) {
            $this->error("Command '{$id}' not found.");

            return self::FAILURE;
        }

        if ($enable) {
            method_exists($cmd, 'enable') ? $cmd->enable() : $cmd->forceFill(['enabled' => true])->save();
            $this->info("Command {$cmd->command_id} enabled.");
        } else {
            method_exists($cmd, 'disable') ? $cmd->disable() : $cmd->forceFill(['enabled' => false])->save();
            $this->info("Command {$cmd->command_id} disabled.");
        }

        return self::SUCCESS;
    }
}
