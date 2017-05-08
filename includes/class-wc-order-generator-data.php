<?php


if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Order_Generator_Data {

	/**
	 * Produce a username.
	 * @return string
	 */
	public function get_username() {
		$t = time();
		$r = rand();
		return "user-$t-$r.png";
	}

	/**
	 * Returns a randomly constructed word based on our syllables.
	 * First letter is uppercase.
	 * 
	 * @param number $min minimum number of syllables (default 1)
	 * @param number $max maximum number of syllables (default 5)
	 * @return string
	 */
	public function get_uc_random_word( $min = 1, $max = 5 ) {
		$result = '';
		$syllables = WC_Order_Generator_Syllables::get_syllables();
		$n = rand( $min, $max );
		for ( $i=0; $i < $n; $i++ ) {
			$result .= $syllables[rand( 0, count( $syllables ) - 1 )];
		}
		$result = ucfirst( $result );
		return $result;
	}

	/**
	 * Constructs a random first name.
	 * @return string
	 */
	public function get_first_name() {
		return $this->get_uc_random_word( 1, 3 );
	}

	/**
	 * Constructs a random last name.
	 * @return string
	 */
	public function get_last_name() {
		return $this->get_uc_random_word( 2, 6 );
	}

	/**
	 * Constructs a random city name.
	 * @return string
	 */
	public function get_city() {
		return $this->get_uc_random_word( 3, 7 );
	}

	/**
	 * Constructs a random street name.
	 * @return string
	 */
	public function get_street() {
		return $this->get_uc_random_word( 2, 5 );
	}

	/**
	 * Constructs a random postcode.
	 * @return string
	 */
	public function get_postcode() {
		return strtoupper( $this->get_uc_random_word( 2, 3 ) );
	}

	/**
	 * Constructs a random phone number.
	 * @return string
	 */
	public function get_phone() {
		$result = '';
		for ( $i = 0; $i < 9; $i++ ) {
			$result .= '' . rand( 0 , 9 );
		}
		return $result;
	}

	/**
	 * Creates a new user and returns the user ID or null on failure.
	 * @return int or null
	 */
	public function create_user() {
		global $wpdb;

		$user_id = null;

		$username = $this->get_username();
		$user = array(
			'user_login'    => $username,
			'user_pass'     => $username,
			'user_email'    => $username . '@example.com',
			'first_name'    => $this->get_first_name(),
			'last_name'     => $this->get_last_name(),
			'role'          => 'customer'
		);

		$inserted_user_id = wp_insert_user( $user );
		if ( !( $inserted_user_id instanceof WP_Error ) ) {

			$user_id      = $inserted_user_id;
			$wc_countries = new WC_Countries();
			$countries    = $wc_countries->get_allowed_countries();
			$country      = array_rand( $countries, 1 );
			$state        = array_rand( $wc_countries->get_states( $countries ), 1 );
			$city         = $this->get_city();
			$street       = $this->get_street() . ', ' . rand( 1, 1000 );
			$postcode     = $this->get_postcode();
			$phone        = $this->get_phone();

			// billing/shipping address
			$meta = array(
				'billing_country'     => $country,
				'billing_first_name'  => $user['first_name'],
				'billing_last_name'   => $user['last_name'],
				'billing_address_1'   => $street,
				'billing_city'        => $city,
				'billing_state'       => $state,
				'billing_postcode'    => $postcode,
				'billing_email'       => $user['user_email'],
				'billing_phone'       => $phone,
				'shipping_country'    => $country,
				'shipping_first_name' => $user['first_name'],
				'shipping_last_name'  => $user['last_name'],
				'shipping_address_1'  => $street,
				'shipping_city'       => $city,
				'shipping_state'      => $state,
				'shipping_postcode'   => $postcode,
				'shipping_email'      => $user['user_email'],
				'shipping_phone'      => $phone
			);

			foreach ( $meta as $key => $value ) {
				update_user_meta( $user_id, $key, $value );
			}
		}
		return $user_id;
	}

	/**
	 * Returns the ID of a random user or null on failure.
	 * @return NULL|number
	 */
	public function get_random_user_id() {
		global $wpdb;
		$user_id = null;
		if ( $max_user_id = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->users" ) ) {
			$max_user_id = intval( $max_user_id );
			for( $i = 0; $i < $max_user_id; $i++ ) {
				$maybe_user_id = rand( 1, $max_user_id );
				if ( $user = get_user_by( 'id', $maybe_user_id ) ) {
					$user_id = $user->ID;
					break;
				}
			}
		}
		return $user_id;
	}
}
// @todo remove
function test_banana() {
	$d = new WC_Order_Generator_Data();
	$user_id = $d->create_user();
	error_log('created user? : '.var_export($user_id,true));
}
add_action( 'init', 'test_banana' );
