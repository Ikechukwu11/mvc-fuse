<?php

namespace Engine\Support;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Engine\Support\View;

class Paginator implements Countable, IteratorAggregate
{
    protected $items;
    protected $total;
    protected $perPage;
    protected $currentPage;
    protected $lastPage;
    protected $path = '/';
    protected $query = [];
    protected $pageName = 'page';
    protected $onEachSide = 3;

    /**
     * Create a new Paginator instance.
     *
     * @param mixed $items
     * @param int $total
     * @param int $perPage
     * @param int|null $currentPage
     * @param array $options [path, query, pageName]
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [])
    {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $this->setCurrentPage($currentPage, $options['pageName'] ?? 'page');
        $this->path = $options['path'] ?? '/';
        $this->query = $options['query'] ?? [];
        $this->pageName = $options['pageName'] ?? 'page';
        $this->lastPage = max((int) ceil($total / $perPage), 1);
    }

    protected function setCurrentPage($currentPage, $pageName)
    {
        $currentPage = $currentPage ?: (filter_input(INPUT_GET, $pageName, FILTER_VALIDATE_INT) ?: 1);
        return $currentPage > 0 ? $currentPage : 1;
    }

    public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        $parameters = [$this->pageName => $page];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
        }

        return $this->path . '?' . http_build_query($parameters, '', '&');
    }

    public function appends(array $query)
    {
        $this->query = array_merge($this->query, $query);
        return $this;
    }

    public function links($view = 'pagination/default')
    {
        return view($view, ['paginator' => $this]);
    }

    public function items()
    {
        return $this->items;
    }

    public function total()
    {
        return $this->total;
    }

    public function perPage()
    {
        return $this->perPage;
    }

    public function currentPage()
    {
        return $this->currentPage;
    }

    public function lastPage()
    {
        return $this->lastPage;
    }

    public function hasPages()
    {
        return $this->total() > $this->perPage();
    }

    public function onFirstPage()
    {
        return $this->currentPage() <= 1;
    }

    public function hasMorePages()
    {
        return $this->currentPage() < $this->lastPage();
    }

    public function previousPageUrl()
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }
    }

    public function nextPageUrl()
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }
    }

    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get the window of pages to be shown.
     */
    public function elements()
    {
        $window = $this->onEachSide;

        if ($this->lastPage < ($window * 2) + 6) {
            return $this->getUrlRange(1, $this->lastPage);
        }

        $windowStart = $this->currentPage - $window;
        $windowEnd = $this->currentPage + $window;

        if ($windowStart < 1) {
            $windowEnd += (1 - $windowStart);
            $windowStart = 1;
        }

        if ($windowEnd > $this->lastPage) {
            $windowStart -= ($windowEnd - $this->lastPage);
            $windowEnd = $this->lastPage;
        }

        $windowStart = max($windowStart, 1);
        $windowEnd = min($windowEnd, $this->lastPage);

        $elements = $this->getUrlRange($windowStart, $windowEnd);

        // Add sliders if needed (simplified for now, just returning range)
        // Ideally we would add '...' logic here.
        
        return $elements;
    }

    public function getUrlRange($start, $end)
    {
        $range = [];
        for ($i = $start; $i <= $end; $i++) {
            $range[$i] = $this->url($i);
        }
        return $range;
    }
}
