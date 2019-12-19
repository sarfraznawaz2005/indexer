<?php

namespace Sarfraznawaz2005\QueryWatch\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class QueryDetected {
    use SerializesModels;

    /** @var Collection */
    protected $queries;

    public function __construct(Collection $queries)
    {
        $this->queries = $queries;
    }

    /**
     * @return Collection
     */
    public function getQueries(): Collection
    {
        return $this->queries;
    }
}
