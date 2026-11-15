<?php
require __DIR__ . '/includes/bootstrap.php';
$title = 'Explore dormitories'; $where = [public_condition()]; $params = []; $errors = [];
if (get('q') !== '') { $where[] = '(d.name LIKE ? OR d.location LIKE ?)'; $params[] = '%' . get('q') . '%'; $params[] = '%' . get('q') . '%'; }
foreach (['university'=>'university_id','room_type'=>'room_type_id'] as $key=>$column) if (get($key) !== '') { $where[] = "d.$column=?"; $params[] = (int)get($key); }
if (get('location') !== '') { $where[] = 'd.location=?'; $params[] = get('location'); }
foreach (['min_rent'=>['>=','minimum'],'max_rent'=>['<=','maximum']] as $key=>[$op,$label]) if (get($key) !== '') {
    $value = decimal(get($key));
    if ($value === null && !preg_match('/^0(?:\.0{1,2})?$/D', get($key))) $errors[] = "Enter a valid $label rent.";
    else { $where[] = "d.monthly_rent $op ?"; $params[] = $value ?? '0.00'; }
}
if (get('min_rent') !== '' && get('max_rent') !== '' && (float)get('min_rent') > (float)get('max_rent')) $errors[] = 'Minimum rent cannot exceed maximum rent.';
if (get('available') === '1') $where[] = 'd.available_slots>0';
$condition = implode(' AND ', $where);
$sort = ['price_asc'=>'d.monthly_rent ASC,d.id ASC','price_desc'=>'d.monthly_rent DESC,d.id ASC','newest'=>'d.created_at DESC,d.id DESC'][get('sort')] ?? 'd.id ASC';
$total = (int)scalar('SELECT COUNT(*) FROM dormitories d WHERE ' . $condition, $params);
$listings = $errors ? [] : rows(listing_select() . ' WHERE ' . $condition . ' ORDER BY ' . $sort . ' LIMIT 9 OFFSET ' . ((page_number()-1)*9), $params);
$shown = $errors ? 0 : $total;

// Active filters as removable chips (server-rendered, so they work without JavaScript).
$universities = rows('SELECT * FROM universities ORDER BY name'); $roomTypes = rows('SELECT * FROM room_types ORDER BY name');
$lookup = fn(array $list, string $id) => array_column($list, 'name', 'id')[$id] ?? null;
$chipLabels = [
    'q' => fn($v) => '“' . $v . '”',
    'university' => fn($v) => $lookup($universities, $v),
    'room_type' => fn($v) => $lookup($roomTypes, $v),
    'location' => fn($v) => $v,
    'min_rent' => fn($v) => 'From ' . money(decimal($v) ?? '0'),
    'max_rent' => fn($v) => 'Up to ' . money(decimal($v) ?? '0'),
    'available' => fn($v) => $v === '1' ? 'Has available slots' : null,
    'sort' => fn($v) => ['price_asc'=>'Lowest rent first','price_desc'=>'Highest rent first','newest'=>'Newest first'][$v] ?? null,
];
$chips = [];
foreach ($chipLabels as $key => $labeler) {
    if (get($key) === '' || ($text = $labeler(get($key))) === null) continue;
    $rest = array_filter(array_diff_key($_GET, [$key => 1, 'page' => 1]), fn($v) => $v !== '' && $v !== null);
    $chips[] = ['label' => $text, 'href' => url('browse.php' . ($rest ? '?' . http_build_query($rest) : ''))];
}
require __DIR__ . '/includes/header.php';
?>
<section class="page-banner"><div class="container"><span class="eyebrow">Your campus. Your community.</span><h1>Find a place that feels like you.</h1><p>Student stays around Cebu City, all in one place.</p></div></section>
<section class="container section"><div class="browse-layout" data-filter-layout><div class="drawer-backdrop" data-filter-close></div>
<aside class="filter-drawer" id="filters" aria-label="Search filters"><div class="drawer-head"><h2>Filters</h2><button class="drawer-close" type="button" data-filter-close aria-label="Close filters"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
<form method="get" id="filter-form"><div class="form-field"><label for="q">Search dorm or area</label><div class="input-icon"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" id="q" name="q" type="search" value="<?= e(get('q')) ?>" placeholder="Name or neighborhood"></div></div>
<?php
select_field('University', 'university', $universities, get('university'), false, 'All universities');
select_field('Room type', 'room_type', $roomTypes, get('room_type'), false, 'Any room type');
select_field('Neighborhood', 'location', rows('SELECT DISTINCT location id,location name FROM dormitories d WHERE ' . public_condition() . ' ORDER BY location'), get('location'), false, 'Any neighborhood');
echo '<div class="price-pair">'; field('Min rent (₱)', 'min_rent', get('min_rent'), 'number', 'min="0" step="0.01" inputmode="decimal"'); field('Max rent (₱)', 'max_rent', get('max_rent'), 'number', 'min="0" step="0.01" inputmode="decimal"'); echo '</div>';
select_field('Sort by', 'sort', [['id'=>'price_asc','name'=>'Lowest rent'],['id'=>'price_desc','name'=>'Highest rent'],['id'=>'newest','name'=>'Newest first']], get('sort'), false, 'Recommended');
?>
<label class="check-label mb-3"><input type="checkbox" name="available" value="1" <?= get('available') === '1' ? 'checked' : '' ?>> Only rooms with available slots</label>
<div class="filter-actions"><button class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Apply filters</button><a class="btn btn-outline-primary" href="<?= url('browse.php') ?>">Clear filters</a></div></form></aside>
<div class="browse-results"><div class="results-bar"><p><strong><?= $shown ?> <?= $shown === 1 ? 'place' : 'places' ?></strong> to start your next chapter</p><button class="btn btn-outline-primary btn-sm filter-toggle" type="button" data-filter-open aria-expanded="false" aria-controls="filters"><i class="bi bi-sliders" aria-hidden="true"></i> Filters<?= $chips ? ' (' . count($chips) . ')' : '' ?></button><span>Fictional demo listings are labeled</span></div>
<?php if ($chips): ?><div class="chip-row" aria-label="Active filters"><?php foreach ($chips as $chip): ?><a class="filter-chip" href="<?= e($chip['href']) ?>" aria-label="Remove filter: <?= e($chip['label']) ?>"><?= e($chip['label']) ?> <i class="bi bi-x-lg" aria-hidden="true"></i></a><?php endforeach; ?><a class="filter-chip" href="<?= url('browse.php') ?>">Clear all</a></div><?php endif; ?>
<?php errors_box($errors); ?>
<?php if ($listings): ?><div class="cards-grid"><?php listing_cards($listings); ?></div><?php paginate($total); else: ?><div class="empty-state"><i class="bi bi-search" aria-hidden="true"></i><h2>No matching places just yet.</h2><p>Try a different neighborhood or a wider budget.</p><a class="btn btn-primary" href="<?= url('browse.php') ?>">Clear filters</a></div><?php endif; ?></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
