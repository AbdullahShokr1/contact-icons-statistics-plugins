<?php
/*
Plugin Name: أيقونات التواصل والإحصائيات 
Description: إضافة لعرض أيقونات الهاتف والواتساب في المقالات مع عرض إحصائيات دقيقة للنقرات مع تحديد فترة زمنية وتفاصيل أخرى.
Author: عبدالله شكر
Version: 1.6
*/

// إنشاء جدول جديد لتخزين النقرات
function create_clicks_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'contact_clicks'; // اسم الجدول الجديد
    $charset_collate = $wpdb->get_charset_collate();

    // استعلام لإنشاء الجدول
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        number varchar(255) NOT NULL,
        type varchar(50) NOT NULL,
        click_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // استخدام dbDelta للتأكد من إنشاء الجدول
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'create_clicks_table' );

// حذف الجدول عند إلغاء تنشيط الإضافة (اختياري)
function delete_clicks_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'contact_clicks';
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);
}
register_deactivation_hook( __FILE__, 'delete_clicks_table' );



// إضافة الحقول المخصصة للمقالات
function add_contact_meta_boxes() {
    add_meta_box('contact_meta', 'Contact Numbers', 'display_contact_meta_boxes', 'post', 'side');
}
add_action('add_meta_boxes', 'add_contact_meta_boxes');

// عرض الحقول المخصصة
function display_contact_meta_boxes($post) {
    $phone = get_post_meta($post->ID, 'phone', true);
    $whatsapp = get_post_meta($post->ID, 'whatsapp', true);
    ?>
    <label>رقم الهاتف:</label>
    <input type="text" name="phone" value="<?php echo esc_attr($phone); ?>">

    <label>رقم الواتس:</label>
    <input type="text" name="whatsapp" value="<?php echo esc_attr($whatsapp); ?>">
    <?php
}

// حفظ البيانات المخصصة
function save_contact_meta_data($post_id) {
    if (isset($_POST['phone'])) {
        update_post_meta($post_id, 'phone', sanitize_text_field($_POST['phone']));
    }
    if (isset($_POST['whatsapp'])) {
        update_post_meta($post_id, 'whatsapp', sanitize_text_field($_POST['whatsapp']));
    }
}
add_action('save_post', 'save_contact_meta_data');

// عرض الأيقونات في واجهة المستخدم
function display_contact_icons($content) {
    if (!is_singular('post')) {
        return $content; // عرض الأيقونات فقط في صفحة المقالات الفردية
    }

    global $post;
    $phone = get_post_meta($post->ID, 'phone', true);
    $whatsapp = get_post_meta($post->ID, 'whatsapp', true);

    if ($phone || $whatsapp) {
        $output = '<div class="contact-icons">';
        if ($phone) {
            $output .= '<a href="tel:' . esc_attr($phone) . '" class="phone-icon" data-post-id="' . $post->ID . '" data-number="' . esc_attr($phone) . '">
                            <img src="' . esc_url(plugins_url('phone.png', __FILE__)) . '" width="48" height="48">
                        </a>';
        }
        if ($whatsapp) {
            $output .= '<a href="https://wa.me/' . esc_attr($whatsapp) . '" class="whatsapp-icon" data-post-id="' . $post->ID . '" data-number="' . esc_attr($whatsapp) . '">
                            <img src="' . esc_url(plugins_url('whatsapp.svg', __FILE__)) . '" width="48" height="48">
                        </a>';
        }
        $output .= '</div>';
        return $output . $content;
    }
    return $content;
}
add_filter('the_content', 'display_contact_icons');

// إضافة الاستايل
function enqueue_contact_icons_styles() {
    wp_enqueue_style('contact_icons', plugins_url('css/style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'enqueue_contact_icons_styles');




/**Strat Create */
// تتبع النقرات وحفظها في قاعدة البيانات
function register_contact_click() {
    if (isset($_POST['post_id'], $_POST['type'], $_POST['number'])) {
        global $wpdb;

        $post_id = intval($_POST['post_id']);
        $type = sanitize_text_field($_POST['type']);
        $number = sanitize_text_field($_POST['number']);

        // اسم الجدول الجديد
        $table_name = $wpdb->prefix . 'contact_clicks';

        // إدخال البيانات في الجدول
        $wpdb->insert(
            $table_name,
            [
                'post_id' => $post_id,
                'number' => $number,
                'type' => $type,
                'click_date' => current_time('mysql'),
            ]
        );

        wp_send_json_success(['status' => 'success']);
    }

    wp_send_json_error(['status' => 'error']);
}
add_action('wp_ajax_register_contact_click', 'register_contact_click');
add_action('wp_ajax_nopriv_register_contact_click', 'register_contact_click');



// إضافة صفحة الإحصائيات إلى لوحة التحكم
function add_contact_stats_page() {
    add_menu_page(
        'إحصائيات الاتصال',
        'إحصائيات الاتصال',
        'manage_options',
        'contact_stats',
        'render_contact_stats_page',
        'dashicons-chart-bar',
        25
    );
}
add_action('admin_menu', 'add_contact_stats_page');

// عرض صفحة الإحصائيات
function render_contact_stats_page() {
    global $wpdb;

    // الحصول على قيم الفلاتر (إذا كانت موجودة)
    $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
    $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
    $filter_number = isset($_GET['filter_number']) ? sanitize_text_field($_GET['filter_number']) : '';
    $filter_title = isset($_GET['filter_title']) ? sanitize_text_field($_GET['filter_title']) : '';

    echo '<div class="wrap">';
    echo '<h1>إحصائيات الاتصال</h1>';
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="contact_stats">';
    
    // حقل تحديد تاريخ البداية
    echo '<label>من تاريخ: </label>';
    echo '<input type="date" name="filter_date_from" value="' . esc_attr($filter_date_from) . '">';
    
    // حقل تحديد تاريخ النهاية
    echo '<label>إلى تاريخ: </label>';
    echo '<input type="date" name="filter_date_to" value="' . esc_attr($filter_date_to) . '">';
    
    echo '<label>الرقم: </label>';
    echo '<input type="text" name="filter_number" placeholder="رقم الهاتف أو الواتساب" value="' . esc_attr($filter_number) . '">';
    echo '<label>عنوان المقالة: </label>';
    echo '<input type="text" name="filter_title" placeholder="عنوان المقالة" value="' . esc_attr($filter_title) . '">';
    echo '<button type="submit" class="button button-primary">بحث</button>';
    echo '</form>';
    echo '<br>';

    // استعلام للحصول على النقرات من الجدول الجديد
    // استعلام للحصول على النقرات من الجدول الجديد
    $query = "SELECT p.ID, p.post_title, c.number, c.type, COUNT(c.id) AS clicks_count, MIN(c.click_date) AS first_seen, MAX(c.click_date) AS last_seen
              FROM {$wpdb->prefix}posts p
              LEFT JOIN {$wpdb->prefix}contact_clicks c ON p.ID = c.post_id
              WHERE p.post_type = 'post' ";

    // إضافة الفلاتر بناءً على المدخلات
    if ($filter_title) {
        $query .= $wpdb->prepare(" AND p.post_title LIKE %s", '%' . $filter_title . '%');
    }

    if ($filter_number) {
        $query .= $wpdb->prepare(" AND c.number LIKE %s", '%' . $filter_number . '%');
    }

    // فلترة حسب تاريخ البداية والنهاية
    if ($filter_date_from) {
        $query .= $wpdb->prepare(" AND DATE(c.click_date) >= %s", $filter_date_from);
    }

    if ($filter_date_to) {
        $query .= $wpdb->prepare(" AND DATE(c.click_date) <= %s", $filter_date_to);
    }

    $query .= " GROUP BY p.ID, c.number, c.type ORDER BY p.post_title";

    // تنفيذ الاستعلام
    $results = $wpdb->get_results($query);

    // عرض النتائج
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr><th>عنوان المقالة</th><th>الرقم</th><th>نوع الرقم</th><th>عدد النقرات</th><th>أول نقرة</th><th>آخر نقرة</th></tr>';
    echo '</thead><tbody>';

    if ($results) {
        foreach ($results as $row) {
            echo '<tr>';
            echo '<td>' . esc_html($row->post_title) . '</td>';
            echo '<td>' . esc_html($row->number) . '</td>';
            echo '<td>' . esc_html($row->type) . '</td>';
            echo '<td>' . esc_html($row->clicks_count) . '</td>';
            echo '<td>' . esc_html($row->first_seen) . '</td>';
            echo '<td>' . esc_html($row->last_seen) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6">لا توجد بيانات لعرضها.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}


// معالجة بيانات النقرات
// معالجة بيانات النقرات
function process_clicks_data($post_id, $filter_date, $filter_number) {
    $phone_clicks = get_post_meta($post_id, 'phone_clicks', true);
    $whatsapp_clicks = get_post_meta($post_id, 'whatsapp_clicks', true);

    // التأكد من أن القيم نصية قبل فك JSON
    $phone_clicks = is_string($phone_clicks) ? json_decode($phone_clicks, true) : [];
    $whatsapp_clicks = is_string($whatsapp_clicks) ? json_decode($whatsapp_clicks, true) : [];

    // التأكد أن القيم مصفوفة
    $phone_clicks = is_array($phone_clicks) ? $phone_clicks : [];
    $whatsapp_clicks = is_array($whatsapp_clicks) ? $whatsapp_clicks : [];

    // دمج النقرات حسب الرقم
    $clicks_by_number = [];

    foreach (['phone' => $phone_clicks, 'whatsapp' => $whatsapp_clicks] as $type => $clicks) {
        foreach ($clicks as $click) {
            if (!isset($click['date'], $click['number'])) {
                continue; // تجاهل البيانات غير الصالحة
            }

            $date = substr($click['date'], 0, 10);  // استخراج التاريخ
            $time = substr($click['date'], 11, 5);  // استخراج الوقت
            $number = $click['number'];

            if (!isset($clicks_by_number[$number])) {
                $clicks_by_number[$number] = [
                    'phone_clicks' => 0,
                    'whatsapp_clicks' => 0,
                    'first_seen' => $date,
                    'last_seen' => $date,
                    'phone_clicks_details' => [],
                    'whatsapp_clicks_details' => [],
                ];
            }

            if ($type === 'phone') {
                $clicks_by_number[$number]['phone_clicks']++;
                $clicks_by_number[$number]['phone_clicks_details'][] = [
                    'date' => $date,
                    'time' => $time,
                ];
            } else {
                $clicks_by_number[$number]['whatsapp_clicks']++;
                $clicks_by_number[$number]['whatsapp_clicks_details'][] = [
                    'date' => $date,
                    'time' => $time,
                ];
            }

            // تحديث أول وآخر ظهور للرقم
            $clicks_by_number[$number]['last_seen'] = max($clicks_by_number[$number]['last_seen'], $date);
        }
    }

    // تطبيق الفلاتر
    if ($filter_number || $filter_date) {
        foreach ($clicks_by_number as $number => $stats) {
            if ($filter_number && strpos($number, $filter_number) === false) {
                unset($clicks_by_number[$number]);
            }
            if ($filter_date && ($stats['first_seen'] > $filter_date || $stats['last_seen'] < $filter_date)) {
                unset($clicks_by_number[$number]);
            }
        }
    }

    return $clicks_by_number;
}

function enqueue_click_tracking_script() {
    wp_enqueue_script('click-tracking', plugins_url('js/click-tracking.js', __FILE__), array('jquery'), null, true);
    
    wp_localize_script('click-tracking', 'clickTrackingAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'post_id' => get_the_ID(), // التأكد من تمرير ID المقالة الحالية
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_click_tracking_script');