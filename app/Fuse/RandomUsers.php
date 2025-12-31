<?php

namespace App\Fuse;

use Engine\Fuse\Component;
use Engine\Support\Paginator;

/**
 * RandomUsers component renders a paginated list of users from in-memory data.
 */
class RandomUsers extends Component
{
  /** @var array<int, array<string, string|int>> */
  public array $users = [];

  /** @var int */
  public int $page = 1;

  /** @var int */
  public int $perPage = 5;

  /** @var int Tracks previous perPage to detect changes */
  public int $prevPerPage = 5;

  /** @var int */
  public int $total = 0;

  /** @var array */
  public array $queryParams = [];

  /**
   * Initialize random user data.
   *
   * @return void
   */
  public function mount()
  {
    $firstNames = ['Ada', 'Grace', 'Linus', 'Guido', 'Brendan', 'Ken', 'Dennis', 'James', 'Barbara', 'Margaret', 'Tim', 'Bjarne', 'Martin', 'Andrew', 'Edsger'];
    $lastNames = ['Lovelace', 'Hopper', 'Torvalds', 'van Rossum', 'Eich', 'Thompson', 'Ritchie', 'Gosling', 'Liskov', 'Hamilton', 'Berners-Lee', 'Stroustrup', 'Fowler', 'Ng', 'Dijkstra'];
    $cities = ['London', 'New York', 'San Francisco', 'Berlin', 'Tokyo', 'Lagos', 'Nairobi', 'Paris', 'Toronto', 'Sydney', 'Cape Town'];

    $count = 50;
    $users = [];
    for ($i = 1; $i <= $count; $i++) {
      $fn = $firstNames[array_rand($firstNames)];
      $ln = $lastNames[array_rand($lastNames)];
      $name = "{$fn} {$ln}";
      $email = strtolower(str_replace(' ', '.', $name)) . $i . '@example.com';
      $city = $cities[array_rand($cities)];
      $users[] = ['id' => $i, 'name' => $name, 'email' => $email, 'city' => $city];
    }
    $this->users = $users;
    $this->total = count($users);

    // Capture initial query parameters to preserve context (e.g. section=pagination)
    $this->queryParams = $_GET;

    // Initialize from URL query if present
    $pageQ = isset($_GET['page']) ? (int) $_GET['page'] : $this->page;
    $perQ = isset($_GET['perPage']) ? (int) $_GET['perPage'] : 5;
    if ($pageQ < 1) $pageQ = 1;
    if ($perQ < 1) $perQ = 1;
    if ($perQ > 50) $perQ = 50;
    $this->perPage = $perQ;
    $this->page = min(max(1, $pageQ), $this->pages());
    $this->prevPerPage = $this->perPage;
  }

  /**
   * Update perPage via explicit input action.
   *
   * @return void
   */
  public function updatePerPage()
  {
    $val = (int) $this->perPage;
    if ($val < 1) $val = 1;
    if ($val > 50) $val = 50;

    // Calculate new page based on current offset
    $firstItemIndex = ($this->page - 1) * $this->prevPerPage;

    $this->perPage = $val;

    // Calculate the new page number
    $this->page = (int) floor($firstItemIndex / $this->perPage) + 1;
    $this->prevPerPage = $this->perPage;

    // Construct URL with preserved params
    $params = $this->queryParams;
    $params['page'] = $this->page;
    $params['perPage'] = $this->perPage;

    $url = '?' . http_build_query($params);

    // Trigger SPA redirect/navigation
    return $this->redirect($url, true);
  }

  /**
   * Normalize state and react to model changes before render.
   *
   * @return void
   */
  public function rendering()
  {
    // Clamp perPage and detect changes without requiring an explicit action call
    $pp = (int) $this->perPage;
    if ($pp < 1) $pp = 1;
    if ($pp > 50) $pp = 50;
    $this->perPage = $pp;

    if ($this->perPage !== $this->prevPerPage) {
      // Calculate the first item index of the current page
      $firstItemIndex = ($this->page - 1) * $this->prevPerPage;
      // Calculate the new page number for that item
      $this->page = (int) floor($firstItemIndex / $this->perPage) + 1;
      $this->prevPerPage = $this->perPage;
    }

    // Ensure current page is within bounds after any updates
    $pages = $this->pages();
    if ($this->page < 1) $this->page = 1;
    if ($this->page > $pages && $pages !== null) $this->page = $pages;
  }

  /**
   * After render, broadcast current state for URL syncing.
   *
   * @return void
   */
  public function rendered()
  {
    $this->dispatch('random-users-state', [
      'page' => $this->page,
      'perPage' => $this->perPage,
    ]);
  }

  /**
   * Jump to a specific page.
   *
   * @param int $p
   * @return void
   */
  public function pageGo(int $p)
  {
    $p = max(1, min($this->pages(), $p));
    $this->page = $p;
  }

  /**
   * Total number of pages.
   *
   * @return int
   */
  public function pages(): int
  {
    return max(1, (int)ceil($this->total / $this->perPage));
  }

  /**
   * Current page slice.
   *
   * @return array<int, array<string, string|int>>
   */
  public function currentSlice(): array
  {
    $offset = ($this->page - 1) * $this->perPage;
    return array_slice($this->users, $offset, $this->perPage);
  }

  /**
   * Render paginated listing.
   *
   * @return string
   */
  public function render()
  {
    $paginator = new Paginator(
      $this->currentSlice(),
      $this->total,
      $this->perPage,
      $this->page,
      ['path' => '/docs', 'query' => $this->queryParams]
    );

    return view('fuse/random-users', ['paginator' => $paginator]);
  }
}
