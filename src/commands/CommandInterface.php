<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/23
 * Time: 上午10:25
 */

namespace lanzhi\redis\commands;


interface CommandInterface
{
    const STATUS_INIT     = 'init';
    const STATUS_PREPARED = 'prepared';
    const STATUS_EXECUTED = 'executed';

    public static function serialize(string $commandId, array $arguments): string;

    public function getCommandId(): string;

    public function setOptions(array $options): self;
    public function getOptions(): array;

    public function setArguments(array $arguments): self;
    public function getArguments(): array;

    public function prepare(): void;

    public function toFriendlyString(): string;
}