<div style="border:1px solid #e7e9f3; border-radius:12px; overflow:hidden;">
    <div style="display:flex; align-items:center; gap:10px; padding:10px; background:#f6f7ff;">
        <label>Per page:
            <input type="number" min="1" max="50"
              fuse:model="perPage"
              fuse:input.debounce.300="updatePerPage"
              style="width:64px; padding:6px; border:1px solid #cfd3ff; border-radius:6px;">
        </label>
        <span style="margin-left:auto;">Page <?= $paginator->currentPage() ?> of <?= $paginator->lastPage() ?></span>
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
            <?php 
            $startSerial = ($paginator->currentPage() - 1) * $paginator->perPage() + 1;
            foreach ($paginator->items() as $i => $u): 
                $serial = $startSerial + $i;
            ?>
            <tr>
                <td style="padding:8px; border-bottom:1px solid #e7e9f3;"><?= $serial ?></td>
                <td style="padding:8px; border-bottom:1px solid #e7e9f3;"><?= $u['name'] ?></td>
                <td style="padding:8px; border-bottom:1px solid #e7e9f3;"><?= $u['email'] ?></td>
                <td style="padding:8px; border-bottom:1px solid #e7e9f3;"><?= $u['city'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="display:flex; align-items:center; gap:8px; padding:10px; border-top:1px solid #e7e9f3;">
        <?= $paginator->links('pagination/fuse') ?>
        <span style="margin-left:auto;">Total: <?= $paginator->total() ?></span>
    </div>
</div>
