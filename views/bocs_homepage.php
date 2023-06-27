<?php

$current_email = '';
$wp_user = new WP_User( get_current_user_id() );
if ($wp_user) $current_email = $wp_user->user_email;

// check if the wooocmmerce rest api keys were created for bocs
$bocs_woocommerce_public_key = '';
$bocs_woocommerce_secret_key = '';

$options = get_option( 'bocs_plugin_options' );

if (isset($options['bocs_woocommerce_public_key'])){
	if (trim($options['bocs_woocommerce_public_key']) != ''){
		$bocs_woocommerce_public_key = $options['bocs_woocommerce_public_key'];
	}
}

if (isset($options['bocs_woocommerce_secret_key'])){
	if (trim($options['bocs_woocommerce_secret_key']) != ''){
		$bocs_woocommerce_secret_key = $options['bocs_woocommerce_secret_key'];
	}
}

if ( $bocs_woocommerce_public_key == '' || $bocs_woocommerce_secret_key == '' ){

	global $wpdb;

	$user_id = get_current_user_id();
	$permissions = 'read_write';
	$description = "Bocs REST API";
	$consumer_key = 'ck_' . wc_rand_hash();
	$consumer_secret = 'cs_' . wc_rand_hash();

	$bocs_woocommerce_public_key = $consumer_key;
	$bocs_woocommerce_secret_key = $consumer_secret;

	$data = array(
		'user_id'         => $user_id,
		'description'     => $description,
		'permissions'     => $permissions,
		'consumer_key'    => wc_api_hash( $consumer_key ),
		'consumer_secret' => $consumer_secret,
		'truncated_key'   => substr( $consumer_key, -7 ),
	);

	$wpdb->insert(
		$wpdb->prefix . 'woocommerce_api_keys',
		$data,
		array(
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		)
	);

	if ( 0 === $wpdb->insert_id ) {
		throw new Exception( __( 'There was an error generating your API Key.', 'woocommerce' ) );
	} else {
		// save the options
		update_option('bocs_plugin_options', $options);
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
			  <a href="https://wordpress.org/support/plugin/bocs/reviews/#new-post" target="_blank" rel="noopener noreferrer"> Leave a Review </a>
			</span> &nbsp; <span>
			  <a href="https://wordpress.org/support/plugin/bocs/" target="_blank" rel="noopener noreferrer"> Need Help? </a>
			</span>
					</div>
					<div class="logo-img">
                            <a href="https://bocs.io" target="_blank" rel="noopener noreferrer">
							<img height="65" src="<?php echo esc_url(plugins_url("../assets/images/bocs-logo.svg", __FILE__)); ?>" alt="Logo">
						</a>
					</div>
					<h2 class="text-center heading">Create and build custom subscription boxes for your Wordpress customers </h2>
					<div class="text-center intro-video">
						<a href="https://bocs.io" target="_blank" rel="noopener noreferrer">
							<img src="<?php echo esc_url(plugins_url("../assets/images/play-video.png", __FILE__)); ?>"> Watch the Bocs.io Video </a>
					</div>
				</div>
				<div class="email-form">
					<div class="row">
						<div class="col-xs-12 form-container">
							<div class="search-container text-center ">
								<form action="https://dev.app.bocs.io/login" style="padding-top:10px; margin: 0px;" onsubmit="document.getElementById('get-started').disabled = true;" method="get" name="signup">
									<input type="text" placeholder="Enter your email address to continue" id="email" name="email" class="search" required="required" value="<?php echo $current_email?>">
									<h5 class="check-box-text">
										<input type="checkbox" class="check-box" name="consent" value="1" required="">
										<label>I agree to Bocs.io <a href="https://bocs.io/tos" target="_blank" rel="noopener noreferrer">Terms of Service</a> and <a href="https://bocs.io/privacy" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
										</label>
									</h5>
									<button id="get-started" type="submit" class="e-mail-button">
										<span class="text-white">Submit</span>
									</button>

									<input type="hidden" name="bocsnonce" value="<?php echo wp_create_nonce("bocsnonce");?>">
									<input type="hidden" name="site" value="<?php echo site_url();?>">
									<input type="hidden" name="public" value="<?php echo $bocs_woocommerce_public_key ?>"><input type="hidden" name="secret" value="<?php echo $bocs_woocommerce_secret_key ?>">
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
							<h1>“</h1>
							<h4>At last, a simple subscription platform that just works. Fantastic support.</h4>
							<h5>Mickey Kay, NerdStuff</h5>
							<p></p>
						</li>
						<li class="slide text-center">
							<img class="user" src="<?php echo esc_url(plugins_url("../assets/images/bv-testimony-david-attardi.jpg", __FILE__)); ?>">
							<br>
							<p></p>
							<h1>“</h1>
							<h4>Once we installed the plugin &amp; synced, it was super easy to get the subscription platform set up. The smoothest &amp; fastest syncing of products we ever did.</h4>
							<h5>David Attardi, Store Success</h5>
							<p></p>
						</li>
						<li class="slide text-center">
							<img class="user" src="<?php echo esc_url(plugins_url("../assets/images/bv-testimony-ryan-sullivan.png", __FILE__)); ?>">
							<br>
							<p></p>
							<h1>“</h1>
							<h4>Bocs is by far the best custom subscription platform. The choice of widgets allow it to seamlessly integrate with our store and get set up fast.</h4>
							<h5>Ryan Sullivan, Wine Club AU</h5>
							<p></p>
						</li>
						<li class="slide text-center">
							<img class="user" src="<?php echo esc_url(plugins_url("../assets/images/bv-testimony-michael-signorella.jpg", __FILE__)); ?>">
							<br>
							<p></p>
							<h1>“</h1>
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