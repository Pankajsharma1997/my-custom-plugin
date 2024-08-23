<?php
/**
 * Plugin Name: My Custom Plugin
 * Plugin URI: https://example.com/my-custom-plugin
 * Description: This plugin is used for show all the cart details of the user to the admin.
 * Version: 1.0
 * Author: Pankaj Sharma 
 * Author URI: https://example.com
 * License: GPLv2 or later
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Function to enqueue custom styles
function my_custom_plugin_enqueue_styles() {
    // Check if we're on the admin page or front-end page where the form is displayed
    if (is_admin()) {
        // Enqueue the custom CSS for the admin area
        wp_enqueue_style('my-custom-plugin-styles', plugin_dir_url(__FILE__) . 'css/custom-styles.css');
    } else {
        // Enqueue the custom CSS for the front-end
        wp_enqueue_style('my-custom-plugin-styles', plugin_dir_url(__FILE__) . 'css/custom-styles.css');
    }
}

// Hook the function into WordPress
add_action('wp_enqueue_scripts', 'my_custom_plugin_enqueue_styles'); // For front-end
add_action('admin_enqueue_scripts', 'my_custom_plugin_enqueue_styles'); // For admin area




// Function to create the custom table when the plugin is activated
function create_pantable_table() {
    
    global $wpdb;
    
    $table_name = $wpdb->prefix . "pantable";

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      email varchar(100) NOT NULL,
      phone varchar(20) NOT NULL,
      product_details text NOT NULL,
      total int(20) NOT NULL,   
      created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
      expires_at datetime DEFAULT NULL,
      PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Function to handle form submission and display
function custom_form_shortcode( $atts ) {
    if ( isset( $_POST['submit'] ) ) {
        // Validate and sanitize form inputs
        $email = sanitize_email( $_POST['email'] );
        $phone = sanitize_text_field( $_POST['phone'] );

        // Ensure WooCommerce is active and cart is available
        if ( class_exists('WooCommerce') && WC()->cart ) {
            // Get cart details and product information
            $cart_items = WC()->cart->get_cart();
            $product_details = array();
            foreach ( $cart_items as $cart_item_key => $cart_item ) {
                $product_id = $cart_item['product_id'];
                $product_name = get_the_title( $product_id );
                $quantity = $cart_item['quantity'];
                $product = wc_get_product( $product_id );
                 $price = $product->get_price();

                $subtotal = $quantity * $price;

                $product_details[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product_name,
                    'quantity'=> $quantity,
                    'price'=> $price . "\n",
                    'subtotal'=>$subtotal,
                );
                
                 
            }
           
            // Calculate total
            $total = array_sum( wp_list_pluck( $product_details, 'subtotal' ) );

            // Insert data into the custom table, including product details
            global $wpdb;
            $wpdb->insert( $wpdb->prefix . 'pantable', array(
                'email' => $email,
                'phone' => $phone,
                'product_details' => json_encode($product_details),
                'total'=>  $total
            ) );
            $insert_id = $wpdb->insert_id;

            if ( $insert_id ) {
                // Success message
                wp_redirect( get_permalink() );
                exit;
            } else {
                // Error message
                echo '<p>Error inserting data.</p>';
            }
        } else {
            echo '<p>WooCommerce is not active or cart is empty.</p>';
        }
    }

    ob_start();
    ?>
    <form class = "custom-form" method="post">
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>
        <br>
        <label for="phone">Phone:</label>
        <input type="text" name="phone" id="phone" required>
        <br>
        <input type="submit"  class="submit-button" name="submit" value="Submit">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'custom_form', 'custom_form_shortcode' );

// Function to handle plugin activation
function my_plugin_activate() {
    create_pantable_table();
}
register_activation_hook(__FILE__, 'my_plugin_activate');


// Function to handle plugin deactivation
function my_plugin_deactivate() {
    // Code to run on plugin deactivation
}
register_deactivation_hook(__FILE__, 'my_plugin_deactivate');



// Function to add admin menu
function my_custom_plugin_menu() {
    $page_title = 'Checkout Page Details';
    $menu_title = 'Checkout Page Details';
    $capability = 'manage_options';
    $menu_slug  = 'checkout-page-details';
    $function   = 'checkout_page_detail';
    $icon_url   = 'dashicons-media-code';  
    $position   = 4; 

    add_menu_page(
       $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position 
    );
}
add_action( 'admin_menu', 'my_custom_plugin_menu' );
function checkout_page_detail() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pantable';

    // Pagination parameters
    $items_per_page = 3;
    $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Total number of items
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // Fetch data for the current page
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name LIMIT %d OFFSET %d",
        $items_per_page,
        $offset
    );
    $results = $wpdb->get_results($query);

    echo '<div class="wrap">';
    echo '<h1>Checkout Page Detail developed by Pankaj</h1>';

    if (!empty($results)) {
        echo '<table border="1" style="margin: 10px; padding: 5px;">';
        echo'<h2 class="table_heading">  User Cart Page details Table';
        echo '<tr>
              <th>Email</th>
              <th>Phone</th>
              <th>Total</th>
              <th>Action</th>
              </tr>';

        foreach ($results as $result) {
            $escapedProductDetails = htmlspecialchars($result->product_details, ENT_QUOTES, 'UTF-8');
            $escapedTotal          = htmlspecialchars($result->total, ENT_QUOTES, 'UTF-8');

            echo '<tr>';
            echo '<td style="padding:5px;">' . esc_html($result->email) . '</td>';
            echo '<td>' . esc_html($result->phone) . '</td>';
            echo '<td>' . esc_html($result->total) . '</td>';
            echo '<td><button type="button" class="custon_plugin_submit_button" onclick="handleAction(\'' . $escapedProductDetails . '\', \'' . $escapedTotal . '\')">Show More </button></td>';
            echo '</tr>';
        }
        echo '</table>';

        // Pagination controls
        $total_pages = ceil($total_items / $items_per_page);
        echo '<div class="pagination">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $class = ($i == $current_page) ? 'current' : '';
            echo "<a href='?page=checkout-page-details&paged=$i' class='$class'>$i</a> ";
        
        }
        echo '</div>';
    } else {
        echo '<h2>No data found in the "pantable" table.</h2>';
    }

    echo '<script type="text/javascript">
    function handleAction(product_details, total) {
        alert("Product Details: " + product_details + "\\nSum Total: " + total);
    }
    </script>';
    echo '</div>';
}
?>

