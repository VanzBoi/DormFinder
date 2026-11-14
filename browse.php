<?php
require __DIR__ . '/includes/bootstrap.php';
$title = 'Find your next home'; $where = [public_condition()]; $params = []; $errors = [];
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
require __DIR__ . '/includes/header.php';
?>
<section class="page-banner"><div class="container"><span class="eyebrow">YOUR CAMPUS. YOUR COMMUNITY.</span><h1>Find a place that feels like you.</h1><p>Student stays around Cebu City, all in one place.</p></div></section><section class="container section"><form method="get" class="filter-panel"><div class="filter-grid"><?php field('Search dorm or area','q',get('q'),'search','placeholder="Start with a name or neighborhood"'); select_field('University','university',rows('SELECT * FROM universities ORDER BY name'),get('university')); select_field('Room type','room_type',rows('SELECT * FROM room_types ORDER BY name'),get('room_type')); select_field('Neighborhood','location',rows('SELECT DISTINCT location id,location name FROM dormitories d WHERE ' . public_condition() . ' ORDER BY location'),get('location')); field('Minimum rent (₱)','min_rent',get('min_rent'),'number','min="0" step="0.01"'); field('Maximum rent (₱)','max_rent',get('max_rent'),'number','min="0" step="0.01"'); select_field('Sort by','sort',[['id'=>'price_asc','name'=>'Price: low to high'],['id'=>'price_desc','name'=>'Price: high to low'],['id'=>'newest','name'=>'Newest first']],get('sort'),false,'Recommended'); ?><div class="filter-buttons"><button class="btn btn-primary">Find dorms</button><a href="<?= url('browse.php') ?>">Reset</a></div></div><label class="check-label"><input type="checkbox" name="available" value="1" <?= get('available') === '1' ? 'checked' : '' ?>> Only show rooms with available slots</label></form><?php errors_box($errors); ?><div class="results-heading"><?php $shown = $errors ? 0 : $total; ?><p><strong><?= $shown ?> <?= $shown === 1 ? 'place' : 'places' ?></strong> to start your next chapter</p><span>Fictional demo listings are labeled</span></div><?php if ($listings): ?><div class="cards-grid"><?php listing_cards($listings); ?></div><?php paginate($total); else: ?><div class="empty-state"><i class="bi bi-search" aria-hidden="true"></i><h2>No matching places just yet.</h2><p>Try a different neighborhood or a wider budget.</p><a class="btn btn-primary" href="<?= url('browse.php') ?>">Clear filters</a></div><?php endif; ?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
