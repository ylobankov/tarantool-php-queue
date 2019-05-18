<?php

/**
 * This file is part of the Tarantool Queue package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tarantool\Queue;

use Tarantool\Client\Client;

final class Queue
{
    private $client;
    private $name;

    /**
     * @param \Tarantool|Client $client
     * @param string $name
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($client, string $name)
    {
        if ($client instanceof \Tarantool) {
            $client = new TarantoolAdapter($client);
        } elseif (!$client instanceof Client) {
            throw new \InvalidArgumentException(\sprintf(
                '%s() expects parameter 1 to be %s or Tarantool, %s given.',
                __METHOD__, Client::class, \is_object($client) ? \get_class($client) : \gettype($client)
            ));
        }

        $this->client = $client;
        $this->name = $name;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function put($data, array $options = []) : Task
    {
        return Task::createFromTuple(
            $this->client->call("queue.tube.$this->name:put", $data, $options)[0]
        );
    }

    public function take(float $timeout = null) : ?Task
    {
        $result = $this->client->call("queue.tube.$this->name:take", $timeout);

        return empty($result[0]) ? null : Task::createFromTuple($result[0]);
    }

    public function touch(int $taskId, float $increment) : ?Task
    {
        $result = $this->client->call("queue.tube.$this->name:touch", $taskId, $increment);

        return empty($result[0]) ? null : Task::createFromTuple($result[0]);
    }

    public function ack(int $taskId) : Task
    {
        return Task::createFromTuple(
            $this->client->call("queue.tube.$this->name:ack", $taskId)[0]
        );
    }

    public function release(int $taskId, array $options = []) : Task
    {
        return Task::createFromTuple(
            $this->client->call("queue.tube.$this->name:release", $taskId, $options)[0]
        );
    }

    public function peek(int $taskId) : Task
    {
        return Task::createFromTuple(
            $this->client->call("queue.tube.$this->name:peek", $taskId)[0]
        );
    }

    public function bury(int $taskId) : Task
    {
        return Task::createFromTuple(
            $this->client->call("queue.tube.$this->name:bury", $taskId)[0]
        );
    }

    public function kick(int $count) : int
    {
        return $this->client->call("queue.tube.$this->name:kick", $count)[0];
    }

    public function delete(int $taskId) : Task
    {
        return Task::createFromTuple(
            $this->client->call("queue.tube.$this->name:delete", $taskId)[0]
        );
    }

    public function truncate() : void
    {
        $this->client->call("queue.tube.$this->name:truncate");
    }

    /**
     * @param string|null $path
     *
     * @throws \InvalidArgumentException
     *
     * @return array|int
     */
    public function stats(string $path = null)
    {
        [$stats] = $this->client->call('queue.stats', $this->name);

        if (null === $path) {
            return $stats;
        }

        foreach (\explode('.', $path) as $key) {
            if (!isset($stats[$key])) {
                throw new \InvalidArgumentException(\sprintf('Invalid path "%s".', $path));
            }
            $stats = $stats[$key];
        }

        return $stats;
    }

    public function call(string $methodName, ...$args) : array
    {
        return $this->client->call("queue.tube.$this->name:$methodName", ...$args);
    }
}
