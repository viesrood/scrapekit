<?php

declare(strict_types=1);

namespace viesrood\scrapekit\dom;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use LogicException;
use Traversable;

/**
 * An immutable list of matched nodes.
 *
 * Behaves like an array in Twig (`|length`, `[0]`, `for` loops, `|slice`) and
 * adds list helpers: `first()`, `last()`, `eq(i)`, `texts()`, `attrs(name)`.
 *
 * @implements IteratorAggregate<int, Node>
 * @implements ArrayAccess<int, Node>
 */
class NodeList implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @param Node[] $nodes
     */
    public function __construct(
        private readonly array $nodes = [],
    ) {
    }

    /**
     * The first node, or null when the list is empty.
     */
    public function first(): ?Node
    {
        return $this->nodes[0] ?? null;
    }

    /**
     * The last node, or null when the list is empty.
     */
    public function last(): ?Node
    {
        return $this->nodes !== [] ? $this->nodes[count($this->nodes) - 1] : null;
    }

    /**
     * The node at the given position, or null when out of range.
     */
    public function eq(int $index): ?Node
    {
        return $this->nodes[$index] ?? null;
    }

    /**
     * The text content of every node in the list.
     *
     * @return string[]
     */
    public function texts(): array
    {
        return array_map(static fn(Node $node): string => $node->text(), $this->nodes);
    }

    /**
     * The value of the given attribute for every node in the list.
     *
     * @return string[]
     */
    public function attrs(string $name): array
    {
        return array_map(static fn(Node $node): string => $node->attr($name), $this->nodes);
    }

    public function count(): int
    {
        return count($this->nodes);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->nodes);
    }

    public function offsetExists(mixed $offset): bool
    {
        // Non-integer offsets are not list indexes; returning false here lets
        // Twig fall through to method resolution (e.g. `list.first`).
        return is_int($offset) && isset($this->nodes[$offset]);
    }

    public function offsetGet(mixed $offset): ?Node
    {
        return is_int($offset) ? ($this->nodes[$offset] ?? null) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('NodeList is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('NodeList is immutable.');
    }
}
