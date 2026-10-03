<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Locations';
$activeMenu  = 'locations';
$plugins     = ['datatables'];
$breadcrumbs = [['label' => 'Locations']];

$cities      = api_list('locations.cities', [], 'cities')['items'];
$areaResult  = api_list('locations.areas', [], 'areas');
$areas       = $areaResult['items'];
$totalServices = (int) ($areaResult['total_services'] ?? 0);
$pincodes    = api_list('locations.pincodes', [], 'PIN codes')['items'];
$services    = api('services.options', [], ['items' => []])['items'];

// Services grouped by category for the mapping screen
$servicesByCategory = [];
foreach ($services as $s) {
    $servicesByCategory[$s['category'] ?: 'Uncategorised'][] = $s;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="page-header">
    <div>
        <h2>Locations</h2>
        <p>Control where services are available. Customers can only book in enabled cities, areas and PIN codes.</p>
    </div>
</div>

<div class="card">
    <ul class="nav nav-tabs-line px-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cities" type="button"><i class="bi bi-buildings me-1"></i> Cities <span class="badge badge-soft-secondary"><?= count($cities) ?></span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#areas" type="button"><i class="bi bi-pin-map me-1"></i> Areas / Localities <span class="badge badge-soft-secondary"><?= count($areas) ?></span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pincodes" type="button"><i class="bi bi-mailbox me-1"></i> PIN Codes <span class="badge badge-soft-secondary"><?= count($pincodes) ?></span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#mapping" type="button"><i class="bi bi-diagram-2 me-1"></i> Assign Services to Location</button></li>
    </ul>

    <div class="tab-content">
        <!-- Cities -->
        <div class="tab-pane fade show active" id="cities" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center p-3 gap-2 flex-wrap">
                <div class="search-input" style="max-width:320px;flex:1">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Search cities" data-dt-search="#citiesTable">
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cityModal" data-title="Add City" data-fill='{"status":true}'><i class="bi bi-plus-lg me-1"></i> Add City</button>
            </div>
            <table class="table table-hover js-datatable" id="citiesTable" data-empty="No cities yet. Add a city, then link areas to it.">
                <thead><tr><th>City</th><th>State</th><th class="text-center">Areas</th><th class="text-center">PIN Codes</th><th>Status</th><th class="text-end" data-orderable="false">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($cities as $c): ?>
                    <tr>
                        <td><span class="cell-title"><?= e($c['name']) ?></span></td>
                        <td><?= e($c['state']) ?></td>
                        <td class="text-center fw-bold"><?= (int) $c['areas'] ?></td>
                        <td class="text-center fw-bold"><?= (int) $c['pincodes'] ?></td>
                        <td><?= statusSwitch($c['status'], $c['name'], 'Enabled', 'Disabled', (string) $c['id'], 'locations.city_status') ?></td>
                        <td>
                            <div class="table-actions justify-content-end">
                                <button type="button" class="btn btn-icon btn-soft-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#cityModal" data-title="Edit City"
                                        data-fill="<?= jsonAttr(['id' => $c['id'], 'name' => $c['name'], 'state' => $c['state'], 'status' => $c['status']]) ?>"><i class="bi bi-pencil"></i></button>
                                <?php if (isSuperAdmin()): ?>
                                    <?= deleteButton('this city', (string) $c['id'], true, 'locations.city_delete') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Areas -->
        <div class="tab-pane fade" id="areas" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center p-3 gap-2 flex-wrap">
                <div class="d-flex gap-2 flex-wrap" style="flex:1">
                    <div class="search-input" style="max-width:280px;flex:1">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control" placeholder="Search areas" data-dt-search="#areasTable">
                    </div>
                    <select class="form-select" style="max-width:200px" data-dt-filter="#areasTable" data-column="1" data-exact aria-label="City">
                        <option value="">All cities</option>
                        <?php foreach ($cities as $c): ?><option><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#areaModal" data-title="Add Area" data-fill='{"status":true}'><i class="bi bi-plus-lg me-1"></i> Add Area</button>
            </div>
            <table class="table table-hover js-datatable" id="areasTable" data-empty="No serviceable areas yet.">
                <thead><tr><th>Area / Locality</th><th>City</th><th>PIN Code</th><th class="text-center">Services</th><th>Status</th><th class="text-end" data-orderable="false">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($areas as $a): ?>
                    <tr>
                        <td><span class="cell-title"><?= e($a['name']) ?></span></td>
                        <td><?= $a['city'] !== '' ? e($a['city']) : '<span class="text-muted">-</span>' ?></td>
                        <td class="font-monospace"><?= e($a['pincode']) ?></td>
                        <td class="text-center"><span class="badge badge-soft-primary" title="Services mapped to this area"><?= (int) $a['services'] ?> / <?= $totalServices ?></span></td>
                        <td><?= statusSwitch($a['status'], $a['name'], 'Enabled', 'Disabled', (string) $a['id'], 'locations.area_status') ?></td>
                        <td>
                            <div class="table-actions justify-content-end">
                                <button type="button" class="btn btn-icon btn-soft-warning" title="Assign services" data-map-area="<?= e($a['id']) ?>" data-map-city="<?= e($a['city_id'] ?? '') ?>"><i class="bi bi-diagram-2"></i></button>
                                <button type="button" class="btn btn-icon btn-soft-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#areaModal" data-title="Edit Area"
                                        data-fill="<?= jsonAttr(['id' => $a['id'], 'name' => $a['name'], 'city' => (string) ($a['city_id'] ?? ''), 'pincode' => $a['pincode'], 'status' => $a['status']]) ?>"><i class="bi bi-pencil"></i></button>
                                <?php if (isSuperAdmin()): ?>
                                    <?= deleteButton('this area', (string) $a['id'], true, 'locations.area_delete') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- PIN codes -->
        <div class="tab-pane fade" id="pincodes" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center p-3 gap-2 flex-wrap">
                <div class="search-input" style="max-width:320px;flex:1">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Search PIN codes" data-dt-search="#pinTable">
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pinModal" data-title="Add PIN Code" data-fill='{"status":true}' <?= $cities ? '' : 'disabled title="Add a city first"' ?>><i class="bi bi-plus-lg me-1"></i> Add PIN Code</button>
                </div>
            </div>
            <table class="table table-hover js-datatable" id="pinTable" data-empty="No PIN codes yet.">
                <thead><tr><th>PIN Code</th><th>Area</th><th>City</th><th>Serviceable</th><th class="text-end" data-orderable="false">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($pincodes as $p): ?>
                    <tr>
                        <td class="font-monospace fw-bold text-heading"><?= e($p['code']) ?></td>
                        <td><?= e($p['area'] ?: '-') ?></td>
                        <td><?= e($p['city'] ?: '-') ?></td>
                        <td><?= statusSwitch($p['status'], $p['code'], 'Enabled', 'Disabled', (string) $p['id'], 'locations.pincode_status') ?></td>
                        <td>
                            <div class="table-actions justify-content-end">
                                <button type="button" class="btn btn-icon btn-soft-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#pinModal" data-title="Edit PIN Code"
                                        data-fill="<?= jsonAttr(['id' => $p['id'], 'code' => $p['code'], 'area' => $p['area'], 'city' => (string) ($p['city_id'] ?? ''), 'status' => $p['status']]) ?>"><i class="bi bi-pencil"></i></button>
                                <?php if (isSuperAdmin()): ?>
                                    <?= deleteButton('this PIN code', (string) $p['id'], true, 'locations.pincode_delete') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Service mapping -->
        <div class="tab-pane fade p-3 p-md-4" id="mapping" role="tabpanel">
            <form class="needs-validation" novalidate data-api="locations.map_save" data-no-reload id="mapForm">
                <div class="row g-3 align-items-end mb-4">
                    <div class="col-md-4">
                        <label class="form-label" for="mapCity">City</label>
                        <select class="form-select" id="mapCity" data-child="#mapArea">
                            <option value="">All cities</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="mapArea">Area / locality</label>
                        <select class="form-select" id="mapArea" name="area" required>
                            <option value="">Select area</option>
                            <?php foreach ($areas as $a): ?>
                                <option value="<?= e($a['id']) ?>" data-parent="<?= e($a['city_id'] ?? '') ?>"><?= e($a['name'] . ($a['city'] ? ' (' . $a['city'] . ')' : ' - ' . $a['pincode'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Select an area.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="mapAll" data-check-all=".svc-check">
                            <label class="form-check-label fw-semibold" for="mapAll">Select all services</label>
                        </div>
                    </div>
                </div>

                <p class="text-muted fs-13" id="mapHint"><i class="bi bi-info-circle me-1"></i> Select an area to load the services currently offered there.</p>

                <div class="row g-3" data-check-scope id="mapServices">
                    <?php foreach ($servicesByCategory as $catName => $list): ?>
                        <?php $catId = 'cat' . md5($catName); ?>
                        <div class="col-md-6 col-xl-4" data-check-scope>
                            <div class="service-check-group">
                                <h6>
                                    <span><?= e($catName) ?></span>
                                    <span class="form-check m-0">
                                        <input class="form-check-input" type="checkbox" id="<?= $catId ?>" data-check-all=".svc-check" title="Select all in <?= e($catName) ?>">
                                        <label class="form-check-label fs-12 text-muted" for="<?= $catId ?>">All</label>
                                    </span>
                                </h6>
                                <?php foreach ($list as $s): ?>
                                    <div class="form-check">
                                        <input class="form-check-input svc-check" type="checkbox" name="services[]" value="<?= (int) $s['id'] ?>" id="svc<?= (int) $s['id'] ?>">
                                        <label class="form-check-label" for="svc<?= (int) $s['id'] ?>">
                                            <?= e($s['name']) ?>
                                            <?php if (!$s['status']): ?><span class="badge badge-soft-muted ms-1">Disabled</span><?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$servicesByCategory): ?>
                        <div class="col-12"><?= emptyState('No services yet. Add services first.', 'bi-tools') ?></div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Availability</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- City modal -->
<div class="modal fade js-crud-modal" id="cityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="locations.city_save">
                <input type="hidden" name="id">
                <div class="modal-header"><h5 class="modal-title" data-modal-title>Add City</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required" for="cityName">City name</label>
                        <input type="text" class="form-control" id="cityName" name="name" required maxlength="100">
                        <div class="invalid-feedback">City name is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="cityState">State</label>
                        <select class="form-select" id="cityState" name="state" required>
                            <option value="">Select state</option>
                            <option>Telangana</option>
                            <option>Andhra Pradesh</option>
                            <option>Karnataka</option>
                            <option>Tamil Nadu</option>
                            <option>Maharashtra</option>
                        </select>
                        <div class="invalid-feedback">Select a state.</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input js-status-toggle" type="checkbox" id="cityStatus" name="status" data-on="Enabled" data-off="Disabled" data-silent checked>
                        <label class="form-check-label" for="cityStatus">Enabled</label>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Area modal -->
<div class="modal fade js-crud-modal" id="areaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="locations.area_save">
                <input type="hidden" name="id">
                <div class="modal-header"><h5 class="modal-title" data-modal-title>Add Area</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="areaCity">City</label>
                        <select class="form-select" id="areaCity" name="city">
                            <option value="">No city</option>
                            <?php foreach ($cities as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Select a valid city.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="areaName">Area / locality name</label>
                        <input type="text" class="form-control" id="areaName" name="name" required maxlength="100">
                        <div class="invalid-feedback">Area name is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="areaPin">PIN code</label>
                        <input type="text" class="form-control" id="areaPin" name="pincode" required pattern="[1-9][0-9]{5}" maxlength="6" inputmode="numeric">
                        <div class="invalid-feedback">Enter a valid 6-digit PIN code.</div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input js-status-toggle" type="checkbox" id="areaStatus" name="status" data-on="Enabled" data-off="Disabled" data-silent checked>
                        <label class="form-check-label" for="areaStatus">Enabled</label>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<!-- PIN code modal -->
<div class="modal fade js-crud-modal" id="pinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form class="needs-validation" novalidate data-api="locations.pincode_save">
                <input type="hidden" name="id">
                <div class="modal-header"><h5 class="modal-title" data-modal-title>Add PIN Code</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required" for="pinCode">PIN code</label>
                        <input type="text" class="form-control" id="pinCode" name="code" required pattern="[1-9][0-9]{5}" maxlength="6" inputmode="numeric">
                        <div class="invalid-feedback">Enter a valid 6-digit PIN code.</div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label required" for="pinCity">City</label>
                            <select class="form-select" id="pinCity" name="city" required>
                                <option value="">Select</option>
                                <?php foreach ($cities as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Select a city.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="pinArea">Area label</label>
                            <input type="text" class="form-control" id="pinArea" name="area" maxlength="120">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input js-status-toggle" type="checkbox" id="pinStatus" name="status" data-on="Enabled" data-off="Disabled" data-silent checked>
                        <label class="form-check-label" for="pinStatus">Enabled</label>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    (function () {
        const areaSelect = document.getElementById('mapArea');
        const hint = document.getElementById('mapHint');
        const boxes = () => Array.from(document.querySelectorAll('#mapServices .svc-check'));
        const masters = () => Array.from(document.querySelectorAll('#mapForm [data-check-all]'));
        const setEnabled = on => [...boxes(), ...masters()].forEach(cb => { cb.disabled = !on; });
        setEnabled(false);

        // Load the services currently mapped to the chosen area
        areaSelect.addEventListener('change', function () {
            masters().forEach(m => { m.checked = false; });
            boxes().forEach(cb => { cb.checked = false; });
            if (!areaSelect.value) { setEnabled(false); hint.classList.remove('d-none'); return; }
            hint.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading services for this area...';
            hint.classList.remove('d-none');
            setEnabled(false);
            AdminApi.get('locations.area_services', { area_id: areaSelect.value }).then(res => {
                const mapped = (res.data.services || []).map(String);
                boxes().forEach(cb => { cb.checked = mapped.includes(cb.value); });
                setEnabled(true);
                hint.innerHTML = '<i class="bi bi-info-circle me-1"></i> ' + mapped.length + ' service(s) currently offered in ' + res.data.area + '.';
            }).catch(err => {
                hint.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-octagon me-1"></i>' + err.message + '</span>';
            });
        });

        // "Assign services" on an area row jumps to the mapping tab with that area selected
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-map-area]');
            if (!btn) return;
            const city = document.getElementById('mapCity');
            city.value = btn.dataset.mapCity || '';
            city.dispatchEvent(new Event('change'));
            areaSelect.value = btn.dataset.mapArea;
            areaSelect.dispatchEvent(new Event('change'));
            bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#mapping"]')).show();
        });
    })();
</script>
<?php
$pageScripts = ob_get_clean();
include __DIR__ . '/includes/footer.php';
