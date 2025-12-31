<?php

namespace App\Fuse;

use Engine\Fuse\Component;

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
    // Initialize from URL query if present
    $pageQ = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perQ = isset($_GET['perPage']) ? (int) $_GET['perPage'] : 5;
    if ($pageQ < 1) $pageQ = 1;
    if ($perQ < 1) $perQ = 1;
    if ($perQ > 50) $perQ = 50;
    $this->perPage = $perQ;
    $this->page = min(max(1, $pageQ), $this->pages());
    $this->prevPerPage = $this->perPage;
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
    if ($this->page > $pages) $this->page = $pages;
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
   * Go to the previous page.
   *
   * @return void
   */
  public function pagePrev()
  {
    $this->page = max(1, $this->page - 1);
  }

  /**
   * Go to the next page.
   *
   * @return void
   */
  public function pageNext()
  {
    $this->page = min($this->pages(), $this->page + 1);
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
   * Update per-page size.
   *
   * @param int $n
   * @return void
   */
  // setPerPage() no longer required; rendering() handles perPage normalization and reset

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
    $rows = '';
    $startSerial = ($this->page - 1) * $this->perPage + 1;
    foreach ($this->currentSlice() as $i => $u) {
      $serial = $startSerial + $i;
      $rows .= "<tr><td>{$serial}</td><td>{$u['name']}</td><td>{$u['email']}</td><td>{$u['city']}</td></tr>";
    }

    $pages = $this->pages();
    $pageLinks = '';
    $maxShow = min($pages, 8);
    $start = max(1, $this->page - 3);
    $end = min($pages, $start + $maxShow - 1);

    $linkStyle = 'display:inline-block; padding:6px 10px; border:1px solid #e7e9f3; background:#fff; color:#333; text-decoration:none; border-radius:6px; margin-right:4px; cursor:pointer;';

    // Base query params to preserve (e.g. section=pagination)
    $baseParams = $_GET;
    $baseParams['perPage'] = $this->perPage;

    if ($start > 1) {
      $baseParams['page'] = 1;
      $url = '?' . http_build_query($baseParams);
      $pageLinks .= "<a href=\"{$url}\" fuse:click.prevent=\"pageGo(1)\" style=\"{$linkStyle}\">1</a> <span>…</span> ";
    }
    for ($i = $start; $i <= $end; $i++) {
      $active = $i === $this->page ? 'background:#e9edff; color:#1d2dd9; font-weight:600; border-color:#d0d7ff;' : '';
      $baseParams['page'] = $i;
      $url = '?' . http_build_query($baseParams);
      $pageLinks .= "<a href=\"{$url}\" fuse:click.prevent=\"pageGo({$i})\" style=\"{$linkStyle} {$active}\">{$i}</a>";
    }
    if ($end < $pages) {
      $baseParams['page'] = $pages;
      $url = '?' . http_build_query($baseParams);
      $pageLinks .= " <span>…</span> <a href=\"{$url}\" fuse:click.prevent=\"pageGo({$pages})\" style=\"{$linkStyle}\">{$pages}</a>";
    }

    $disabledPrev = $this->page <= 1 ? 'opacity:.5; pointer-events:none;' : '';
    $disabledNext = $this->page >= $pages ? 'opacity:.5; pointer-events:none;' : '';

    $prevPage = max(1, $this->page - 1);
    $nextPage = min($pages, $this->page + 1);

    $baseParams['page'] = $prevPage;
    $prevUrl = '?' . http_build_query($baseParams);

    $baseParams['page'] = $nextPage;
    $nextUrl = '?' . http_build_query($baseParams);

    return <<<HTML
        <div style="border:1px solid #e7e9f3; border-radius:12px; overflow:hidden;">
            <div style="display:flex; align-items:center; gap:10px; padding:10px; background:#f6f7ff;">
                <label>Per page:
                    <input type="number" min="1" max="50" value="{$this->perPage}"
                           fuse:model.number="perPage"
                           style="width:64px; padding:6px; border:1px solid #cfd3ff; border-radius:6px;">
                </label>
                <span style="margin-left:auto;">Page {$this->page} of {$pages}</span>
            </div>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#eef2ff;">
                        <th style="text-align:left; padding:8px; border-bottom:1px solid #e7e9f3;">#</th>
                        <th style="text-align:left; padding:8px; border-bottom:1px solid #e7e9f3;">Name</th>
                        <th style="text-align:left; padding:8px; border-bottom:1px solid #e7e9f3;">Email</th>
                        <th style="text-align:left; padding:8px; border-bottom:1px solid #e7e9f3;">City</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                </tbody>
            </table>
            <div style="display:flex; align-items:center; gap:8px; padding:10px; border-top:1px solid #e7e9f3;">
                <a href="{$prevUrl}" fuse:click.prevent="pagePrev" style="{$linkStyle} {$disabledPrev}">Prev</a>
                {$pageLinks}
                <a href="{$nextUrl}" fuse:click.prevent="pageNext" style="{$linkStyle} {$disabledNext}">Next</a>
                <span style="margin-left:auto;">Total: {$this->total}</span>
            </div>
        </div>
HTML;
  }
}
