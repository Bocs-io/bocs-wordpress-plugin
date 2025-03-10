<?php
/**
 * Bocs Homepage Template
 *
 * @package    Bocs
 * @subpackage Bocs/views
 * @since      0.0.1
 */

// Get current user email
$current_email = '';
$wp_user = new WP_User(get_current_user_id());
if ($wp_user) {
    $current_email = $wp_user->user_email;
}

// Check WooCommerce REST API keys
$bocs_woocommerce_public_key = '';
$bocs_woocommerce_secret_key = '';

$options = get_option('bocs_plugin_options');

if (isset($options['bocs_woocommerce_public_key']) && 
    trim($options['bocs_woocommerce_public_key']) !== '') {
    $bocs_woocommerce_public_key = $options['bocs_woocommerce_public_key'];
}

if (isset($options['bocs_woocommerce_secret_key']) && 
    trim($options['bocs_woocommerce_secret_key']) !== '') {
    $bocs_woocommerce_secret_key = $options['bocs_woocommerce_secret_key'];
}

// Generate API keys if not exist
if (empty($bocs_woocommerce_public_key) || empty($bocs_woocommerce_secret_key)) {
    try {
        global $wpdb;

        $user_id = get_current_user_id();
        $permissions = 'read_write';
        $description = __('Bocs REST API', 'bocs-wordpress');
        $consumer_key = 'ck_' . wc_rand_hash();
        $consumer_secret = 'cs_' . wc_rand_hash();

        $bocs_woocommerce_public_key = $consumer_key;
        $bocs_woocommerce_secret_key = $consumer_secret;

        $data = array(
            'user_id'         => $user_id,
            'description'     => $description,
            'permissions'     => $permissions,
            'consumer_key'    => wc_api_hash($consumer_key),
            'consumer_secret' => $consumer_secret,
            'truncated_key'   => substr($consumer_key, -7),
        );

        $result = $wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        if (0 === $wpdb->insert_id) {
            throw new Exception(__('Critical: Error generating API Key.', 'bocs-wordpress'));
        }

        update_option('bocs_plugin_options', $options);

    } catch (Exception $e) {
        error_log(sprintf(
            /* translators: %s: Error message */
            __('Critical: Failed to generate WooCommerce API keys: %s', 'bocs-wordpress'),
            $e->getMessage()
        ));
    }
}
?>
<div class="bocs">
	<div id="add-new-account">
		<section id="header">
			<div class="custom-container">
				<div class="header-top">
					<div class="top-links">
						<span>
							<a href="https://wordpress.org/support/plugin/bocs/reviews/#new-post" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e('Leave a Review', 'bocs-wordpress'); ?>
							</a>
						</span>
						<span>
							<a href="https://wordpress.org/support/plugin/bocs/" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e('Need Help?', 'bocs-wordpress'); ?>
							</a>
						</span>
					</div>
					<div class="logo-img">
						<a href="https://bocs.io" target="_blank" rel="noopener noreferrer">
							<img height="65" src="<?php echo esc_url(plugins_url('../assets/images/bocs-logo.svg', __FILE__)); ?>" alt="<?php esc_attr_e('Bocs Logo', 'bocs-wordpress'); ?>">
						</a>
					</div>
					<h2 class="text-center heading">
						<?php esc_html_e('Create and build custom subscription boxes for your WordPress customers', 'bocs-wordpress'); ?>
					</h2>
					<div class="text-center intro-video">
						<a href="https://bocs.io" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url(plugins_url('../assets/images/play-video.png', __FILE__)); ?>" alt="<?php esc_attr_e('Play Video', 'bocs-wordpress'); ?>">
							<?php esc_html_e('Watch the Bocs.io Video', 'bocs-wordpress'); ?>
						</a>
					</div>
				</div>
				<div class="email-form">
					<div class="row">
						<div class="col-xs-12 form-container">
							<div class="search-container text-center">
								<form action="https://dev.app.bocs.io/login" 
									  style="padding-top:10px; margin: 0px;" 
									  onsubmit="document.getElementById('get-started').disabled = true;" 
									  method="get" 
									  name="signup">
									<input type="text" 
										   placeholder="<?php esc_attr_e('Enter your email address to continue', 'bocs-wordpress'); ?>" 
										   id="email" 
										   name="email" 
										   class="search" 
										   required="required" 
										   value="<?php echo esc_attr($current_email); ?>">
									<h5 class="check-box-text">
										<input type="checkbox" class="check-box" name="consent" value="1" required="">
										<label>
											<?php 
											printf(
												/* translators: 1: Terms of Service link, 2: Privacy Policy link */
												esc_html__('I agree to Bocs.io %1$s and %2$s', 'bocs-wordpress'),
												sprintf('<a href="https://bocs.io/tos" target="_blank" rel="noopener noreferrer">%s</a>', esc_html__('Terms of Service', 'bocs-wordpress')),
												sprintf('<a href="https://bocs.io/privacy" target="_blank" rel="noopener noreferrer">%s</a>', esc_html__('Privacy Policy', 'bocs-wordpress'))
											);
											?>
										</label>
									</h5>
									<button id="get-started" type="submit" class="e-mail-button">
										<span class="text-white"><?php esc_html_e('Submit', 'bocs-wordpress'); ?></span>
									</button>

									<input type="hidden" name="bocsnonce" value="<?php echo esc_attr(wp_create_nonce('bocsnonce')); ?>">
									<input type="hidden" name="site" value="<?php echo esc_attr(site_url()); ?>">
									<input type="hidden" name="public" value="<?php echo esc_attr($bocs_woocommerce_public_key); ?>">
									<input type="hidden" name="secret" value="<?php echo esc_attr($bocs_woocommerce_secret_key); ?>">
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
		<section id="list-features">
			<div class="custom-container">
				<div class="heading text-center">
					<h5>SUPERCHARGE YOUR SUBSCRIPTION STORE</h5>
					<h4>Build user choice or curated boxes with quantity and frequency variations</h4>
				</div>
				<div class="row">
					<div class="col-xs-12 d-flex">
						<div class="col-xs-12 col-lg-6 px-3">
							<div>
								<img class="main-image" src="<?php echo esc_url(plugins_url("../assets/images/bv-features-list.png", __FILE__)); ?>">
							</div>
							<div class="text-center intro-video d-flex">
								<a href="https://bocs.io" target="_blank" rel="noopener noreferrer">
									<img src="<?php echo esc_url(plugins_url("../assets/images/play-video.png", __FILE__)); ?>"> &nbsp;Watch the Bocs.io Video </a>
							</div>
						</div>
						<div class="col-xs-12 col-lg-6 d-flex px-3">
							<div id="accordion">
								<div>
									<input type="radio" name="accordion-group" id="option-1" checked="">
									<div class="acc-card">
										<label for="option-1">
											<h5>100% Customisable User Choice subscription boxes</h5>
											<h4>Let your customers build their own subscription boxes the way they want</h4>
										</label>
										<div class="article">
											<ul>
												<li>Build collections and boxes with huge amount of options</li>
												<li>Let your customers choose their products</li>
												<li>They decide when to get them delivered</li>
												<li>Add products, remove products whenever they like</li>
											</ul>
										</div>
									</div>
								</div>
								<div>
									<input type="radio" name="accordion-group" id="option-2">
									<div class="acc-card">
										<label for="option-2">
											<h5>Easy box builder with unlimited customizable options</h5>
											<h4>Create widgets, match branding and ensure the best customer experience </h4>
										</label>
										<div class="article">
											<ul>
												<li>Create a unique widget per collection or per box</li>
												<li>Match your website branding and display anywhere</li>
												<li>Create a beautiful usable experience for your customers to build their own box</li>
												<li>Use shortcodes to add to your site and many other ways to integrate</li>
											</ul>
										</div>
									</div>
								</div>
								<div>
									<input type="radio" name="accordion-group" id="option-3">
									<div class="acc-card">
										<label for="option-3">
											<h5>Seamless integration with your store</h5>
											<h4>The easiest and most user friendly app to manage customer subscriptions</h4>
										</label>
										<div class="article">
											<ul>
												<li>Use your current stores payment gateway</li>
												<li>Choose what to sync - products, contacts - 2 way or 1 way</li>
												<li>Extend your store functionality with API's and extensions</li>
												<li>New features added monthly</li>
											</ul>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>
		<section id="testimony">
			<div class="carousel text-center">
				<div class="slide-div text-center">
					<input type="radio" name="slides" id="radio-1" checked="">
					<input type="radio" name="slides" id="radio-2">
					<input type="radio" name="slides" id="radio-3">
					<input type="radio" name="slides" id="radio-4">
					<ul class="slides text-center">
						<li class="slide text-center">
							<img class="user" src="<?php echo esc_url(plugins_url("../assets/images/bv-testimony-mickey-kay.jpeg", __FILE__)); ?>">
							<br>
							<p></p>
							<h1>"</h1>
							<h4>At last, a simple subscription platform that just works. Fantastic support.</h4>
							<h5>Mickey Kay, NerdStuff</h5>
							<p></p>
						</li>
						<li class="slide text-center">
							<img class="user" src="<?php echo esc_url(plugins_url("../assets/images/bv-testimony-david-attardi.jpg", __FILE__)); ?>">
							<br>
							<p></p>
							<h1>"</h1>
							<h4>Once we installed the plugin &amp; synced, it was super easy to get the subscription platform set up. The smoothest &amp; fastest syncing of products we ever did.</h4>
							<h5>David Attardi, Store Success</h5>
							<p></p>
						</li>
						<li class="slide text-center">
							<img class="user" src="<?php echo esc_url(plugins_url("../assets/images/bv-testimony-ryan-sullivan.png", __FILE__)); ?>">
							<br>
							<p></p>
							<h1>"</h1>
							<h4>Bocs is by far the best custom subscription platform. The choice of widgets allow it to seamlessly integrate with our store and get set up fast.</h4>
							<h5>Ryan Sullivan, Wine Club AU</h5>
							<p></p>
						</li>
						<li class="slide text-center">
							<img class="user" src="<?php echo esc_url(plugins_url("../assets/images/bv-testimony-michael-signorella.jpg", __FILE__)); ?>">
							<br>
							<p></p>
							<h1>"</h1>
							<h4>This platform has proved to be invaluable. I tried a few other options to create customer subscription boxes but this surpasses them all. Very pleased.</h4>
							<h5>Michael Signorella, Health Benefits UK</h5>
							<p></p>
						</li>
					</ul>
					<div class="slidesNavigation text-center">
						<label for="radio-1" id="dotForRadio-1"></label>
						<label for="radio-2" id="dotForRadio-2"></label>
						<label for="radio-3" id="dotForRadio-3"></label>
						<label for="radio-4" id="dotForRadio-4"></label>
					</div>
				</div>
			</div>
		</section>
		<section id="footer">
			<div class="custom-container text-center" id="">
				<div class="row">
					<div class="col-lg-12">
						<div class="heading">
							<h5>TRUSTED BY BRANDS WORLDWIDE</h5>
							<h4>Trusted by 1,000's of online stores</h4>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>
</div>