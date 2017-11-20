<?php
/*
Plugin Name: Gundelli Weather
Plugin URI: http://github.com/rgundelli/weather
Description: Reads the apixu.com REST API and Provides a shortcode and widget.
Version: 1.0
Author: Ram Gundelli
Author URI: http://github.com/rgundelli/
Text Domain: gundelli-weather-plugin
License: GPLv3
 */
register_activation_hook(__FILE__, 'gundelli_weather_install');

function gundelli_weather_install(){
	global $wp_version;
	if( version_compare($wp_version, '4.1', '<')){
		wp_die('This plugin requires WordPress Version 4.1 or higher.');
	}

	//setup default option values
	$weather_options_arr = array(
		'currency_sign' => '$'
	);

	//save our default option values
	update_option( 'weather_options', $weather_options_arr );
}

register_deactivation_hook(__FILE__, 'gundelli_weather_deactivate');

function gundelli_weather_deactivate(){
	console.log('Gundelli Weather plugin deactivated successfully.');
}

/**
 * @param $apikey
 * @param $question place or zip
 *
 * @return string url
 * Constructs the apixu.com API URL
 */
function get_weather_source_url( $apikey, $question){
	$url = 'https://api.apixu.com/v1/forecast.json?key=';
	$url .= $apikey . '&q=';
	$url .= $question;
	$url .= '&days=7';
	return $url;
}

/**
 * @param $url
 * Gets JSON from the apixu.com API
 * @return array|mixed|object|string
 */
function get_weather_data_json($url){
	$request = wp_remote_get( $url );
	if( is_wp_error( $request ) ) {
		return 'could not obtain data'; // Bail early
	}else {
		//retreive message body from web service
		$body = wp_remote_retrieve_body( $request );
		//obtain JSON - as object or array
		$data = json_decode( $body, true );
		return $data;
	}
}

add_action( 'widgets_init', 'gundelli_weather_create_widgets' );

function gundelli_weather_create_widgets() {
	register_widget( 'Gundelli_Weather' );
}

class Gundelli_Weather extends WP_Widget {
	// Construction function
	function __construct () {
		parent::__construct( 'Gundelli_Weather', 'Weather',
			array( 'description' =>
				       'Displays current weather from the apixu.com API' ) );
	}
	/**
	 * @param array $instance
	 * Code to show the administrative interface for the Widget
	 */
	function form( $instance ) {
		// Retrieve previous values from instance
		// or set default values if not present
		$weather_api_key = ( !empty( $instance['weather_api_key'] ) ?
			esc_attr( $instance['weather_api_key'] ) :
			'error' );
		$question = ( !empty( $instance['question'] ) ?
			esc_attr( $instance['question'] ) : 'error');
		$widget_title = ( !empty( $instance['widget_title'] ) ?
			esc_attr( $instance['widget_title'] ) :
			'Weather' );
		?>
		<!-- Display fields to specify title and item count -->
		<p>
			<label for="<?php echo
			$this->get_field_id( 'widget_title' ); ?>">
				<?php echo 'Widget Title:'; ?>
				<input type="text"
				       id="<?php echo
				       $this->get_field_id( 'widget_title' );?>"
				       name="<?php
				       echo $this->get_field_name( 'widget_title' ); ?>"
				       value="<?php echo $widget_title; ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo
			$this->get_field_id( 'weather_api_key' ); ?>">
				<?php echo 'Apixu.com API Key:'; ?>
				<input type="text"
				       id="<?php echo
				       $this->get_field_id( 'weather_api_key' );?>"
				       name="<?php
				       echo $this->get_field_name( 'weather_api_key' ); ?>"
				       value="<?php echo $weather_api_key; ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo
			$this->get_field_id( 'question' ); ?>">
				<?php echo 'Place or Zip:'; ?>
				<input type="text"
				       id="<?php echo
				       $this->get_field_id( 'question' );?>"
				       name="<?php
				       echo $this->get_field_name( 'question' ); ?>"
				       value="<?php echo $question; ?>" />
			</label>
		</p>
	<?php }
	/**
	 * @param array $new_instance
	 * @param array $instance
	 *
	 * Code to update the admin interface for the widget
	 *
	 * @return array
	 */
	function update( $new_instance, $instance ) {
		$instance['widget_title'] =
			sanitize_text_field( $new_instance['widget_title'] );
		$instance['weather_api_key'] =
			sanitize_text_field( $new_instance['weather_api_key'] );
		$instance['question'] =
			sanitize_text_field( $new_instance['question'] );
		return $instance;
	}
	/**
	 * @param array $args
	 * @param array $instance
	 *
	 * Code for the display of the widget
	 *
	 */
	function widget( $args, $instance ) {
		// Extract members of args array as individual variables
		extract( $args );
		$widget_title = ( !empty( $instance['widget_title'] ) ?
			esc_attr( $instance['widget_title'] ) :
			'Weather' );
		$weather_api_key = ( !empty( $instance['weather_api_key'] ) ?
			esc_attr( $instance['weather_api_key'] ) :
			'0' );
		$question = ( !empty( $instance['question'] ) ?
			esc_attr( $instance['question'] ) :
			'0' );
		//get URLs
		$forecast_url = get_weather_source_url( $weather_api_key, $question);
		//obtain JSON - as object or array
		$forecast_data = get_weather_data_json($forecast_url);
		// Display widget title
		echo $before_widget . $before_title;
		echo apply_filters( 'widget_title', $widget_title );
		echo $after_title;

		echo $forecast_data['location']['name'] . ", " . $forecast_data['location']['region'] . ", " . $forecast_data['location']['country'];
		echo '<br>';
		echo $forecast_data['location']['localtime'];

		echo '<table style="width:100%"><col width="35%"><col width="20%"><col width="45%"><tr> ' .
		     '<th><img src="' . $forecast_data['current']['condition']['icon'] . '"></th>' .
		     '<th> ' . round($forecast_data['current']['temp_f']) . '°F' . '</th>' .
		     '<th> ' . $forecast_data['current']['condition']['text'] . '</th>' .
		     '</tr></table>';

		echo '7 Day Forecast';

		echo '<table style="' . 'width:100%' . '"><tr><th>Day</th><th>Min °F</th><th>Max °F</th></tr>' .

             '<tr>' .
             '<td>' . date("D", floatval($forecast_data['forecast']['forecastday']['0']['date_epoch'])) . '</td>'.
             '<td>' . round($forecast_data['forecast']['forecastday']['0']['day']['mintemp_f']) . '°' . '</td>' .
             '<td>' . round($forecast_data['forecast']['forecastday']['0']['day']['maxtemp_f']) . '°' . '</td>' .
             '</tr>' .

		     '<tr>' .
		     '<td>' . date("D", floatval($forecast_data['forecast']['forecastday']['1']['date_epoch'])) . '</td>'.
		     '<td>' . round($forecast_data['forecast']['forecastday']['1']['day']['mintemp_f']) . '°' . '</td>' .
		     '<td>' . round($forecast_data['forecast']['forecastday']['1']['day']['maxtemp_f']) . '°' . '</td>' .
		     '</tr>' .

		     '<tr>' .
		     '<td>' . date("D", floatval($forecast_data['forecast']['forecastday']['2']['date_epoch'])) . '</td>'.
		     '<td>' . round($forecast_data['forecast']['forecastday']['2']['day']['mintemp_f']) . '°' . '</td>' .
		     '<td>' . round($forecast_data['forecast']['forecastday']['2']['day']['maxtemp_f']) . '°' . '</td>' .
		     '</tr>' .

		     '<tr>' .
		     '<td>' . date("D", floatval($forecast_data['forecast']['forecastday']['3']['date_epoch'])) . '</td>'.
		     '<td>' . round($forecast_data['forecast']['forecastday']['3']['day']['mintemp_f']) . '°' . '</td>' .
		     '<td>' . round($forecast_data['forecast']['forecastday']['3']['day']['maxtemp_f']) . '°' . '</td>' .
		     '</tr>' .

		     '<tr>' .
		     '<td>' . date("D", floatval($forecast_data['forecast']['forecastday']['4']['date_epoch'])) . '</td>'.
		     '<td>' . round($forecast_data['forecast']['forecastday']['4']['day']['mintemp_f']) . '°' . '</td>' .
		     '<td>' . round($forecast_data['forecast']['forecastday']['4']['day']['maxtemp_f']) . '°' . '</td>' .
		     '</tr>' .

		     '<tr>' .
		     '<td>' . date("D", floatval($forecast_data['forecast']['forecastday']['5']['date_epoch'])) . '</td>'.
		     '<td>' . round($forecast_data['forecast']['forecastday']['5']['day']['mintemp_f']) . '°' . '</td>' .
		     '<td>' . round($forecast_data['forecast']['forecastday']['5']['day']['maxtemp_f']) . '°' . '</td>' .
		     '</tr>' .

		     '<tr>' .
		     '<td>' . date("D", floatval($forecast_data['forecast']['forecastday']['6']['date_epoch'])) . '</td>'.
		     '<td>' . round($forecast_data['forecast']['forecastday']['6']['day']['mintemp_f']) . '°' . '</td>' .
		     '<td>' . round($forecast_data['forecast']['forecastday']['6']['day']['maxtemp_f']) . '°' . '</td>' .
		     '</tr>' .

		     '</table>';

		echo $after_widget;
	}
}
?>