<?php

namespace AntonyThorpe\Consumer;

use ArrayIterator;

/**
 * Array Bulk Loader Source
 * Useful for testing bulk loader. The output is the same as the input.
 */
class ArrayBulkLoaderSource extends BulkLoaderSource
{
    public function __construct(protected array $data)
    {
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
