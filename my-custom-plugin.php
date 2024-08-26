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
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
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
                'total' => $total
            ) );
            $insert_id = $wpdb->insert_id;

            if ( $insert_id ) {
                // Email settings
                $to = "pnkaj.sharma97@gmail.com"; // Admin 
                $subject = 'Order Confirmation';
                $headers = array('Content-Type: text/html; charset=UTF-8');
                
                // Email body
                ob_start();
                ?>
                <h1>Order Confirmation</h1>
                <p>Thank you for your order. Here are the details:</p>
                <p><strong>Email:</strong> <?php echo esc_html( $email ); ?></p>
                <p><strong>Phone:</strong> <?php echo esc_html( $phone ); ?></p>
                <table border="1" style="border-collapse: collapse; width: 100%;">
                    <tr>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                    <?php foreach ( $product_details as $item ) : ?>
                    <tr>
                        <td><?php echo esc_html( $item['product_name'] ); ?></td>
                        <td><?php echo esc_html( $item['quantity'] ); ?></td>
                        <td><?php echo wp_kses_post( wc_price( $item['price'] ) ); ?></td>
                        <td><?php echo wp_kses_post( wc_price( $item['subtotal'] ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                        <td><?php echo wp_kses_post( wc_price( $total ) ); ?></td>
                    </tr>
                </table>
                <?php
                $message = ob_get_clean();

                // Send email
                wp_mail( $to, $subject, $message, $headers );

                // Redirect after successful submission
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
    <form class="custom-form" method="post">
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>
        <br>
        <label for="phone">Phone:</label>
        <input type="text" name="phone" id="phone" required>
        <br>
        <input type="submit" class="submit-button" name="submit" value="Submit">
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


 // Function for show the Product Details in table with Pagination  and add a show more for Pop up  product details.
function checkout_page_detail() {
    global $wpdb;
    $table_name = $wpdb->prefix . "pantable";

    // Pagination
    $items_per_page = 5;
    $current_page = isset($_GET['paged'])? intval($_GET['paged']) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Total number of items 
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // Fetch data for the current Page from the "pantable" on Specific criteria  
    $query = $wpdb-> prepare
    (" SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d ",
    $items_per_page,
    $offset, 
     );

    // Retrieve data from the "pantable" table based on your specific criteria
    $results = $wpdb->get_results($query);
    
    echo '<div class="wrap">';
    echo '<h1>Checkout Page Detail developed by Pankaj</h1>';

    if (!empty($results)) {
     
        // Display table
        echo '<table border="1" style="margin: 10px; padding: 5px;">';
        echo '<tr>
              <th> S.No.   </th> 
              <th> Email   </th> 
              <th> Phone   </th> 
              <th> Total   </th> 
              <th> Action  </th>
              <th> Created At </th> 
              </tr>';

               // Intilize the Serial Number 
               $serialNumber = $offset + 1 ;

        foreach ($results as $result) {
            echo '<tr>';
            echo '<td style = "padding:5px;">' . $serialNumber .            '</td>';
            echo '<td style = "padding:5px;">' . esc_html($result->email) . '</td>';
            echo '<td>' . esc_html($result->phone) . '</td>';
            echo '<td>' . esc_html($result->total) . '</td>';
            echo '<td><button onclick="showDetails(\'' . esc_js($result->product_details) . '\', \'' . esc_html($result->total). '\')">Show More</button></td>';
            echo ' <td>' . esc_html($result->created_at).'</td>';
            echo '</tr>';

            //  Increase the Serial Number Counter 
            $serialNumber++;
        }
        echo '</table>';

        // Modal HTML
        echo '<div id="modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:80%; max-width:600px; padding:20px; background:#fff; border:1px solid #ccc; box-shadow:0 2px 10px rgba(0,0,0,0.2); z-index:1001;">
              <h2>Product Details</h2>
              <div id="modal-content"></div>
              <button onclick="closeModal()">Close</button>
              </div>';

        // Modal overlay
        echo '<div id="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;"></div>';
    
   
        // Pagination Control 
        $total_pages = ceil($total_items / $items_per_page);
        echo'<div class ="pagination">';
        for( $i=1; $i<=$total_pages; $i++){
            $class = ($i == $current_page) ? 'current':'';
            echo"<a href ='?page=checkout-page-details&paged=$i' class='$class'> $i </a>";
        }
        echo'</div>';
    
    
    }
    else {
        echo '<h2>No data found in the "pantable" table.</h2>';
    }

    // JavaScript for modal
    echo '<script>
    function showDetails(details) {
        var modal = document.getElementById("modal");
        var overlay = document.getElementById("modal-overlay");
        var content = document.getElementById("modal-content");
        
        content.innerText = details;
        modal.style.display = "block";
        overlay.style.display = "block";
        
        // Add event listener to close modal when clicking outside
        overlay.addEventListener("click", closeModal);
    }

    function closeModal() {
        var modal = document.getElementById("modal");
        var overlay = document.getElementById("modal-overlay");
        
        modal.style.display = "none";
        overlay.style.display = "none";
        
        // Remove event listener after modal is closed
        overlay.removeEventListener("click", closeModal);
    }
    </script>';
    echo '</div>'; 
}
?>

