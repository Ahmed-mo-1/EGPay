<?php

add_action('init', function () {
    register_post_status('wc-pending-review', array(
        'label' => 'Pending Review',
        'public' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Review <span class="count">(%s)</span>', 'Review <span class="count">(%s)</span>'),
    ));
});
add_filter('wc_order_statuses', function ($statuses) {
    $statuses['wc-pending-review'] = 'Pending Review';
    return $statuses;
});