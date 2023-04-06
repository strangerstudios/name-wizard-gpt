<?php
/*
Plugin Name: Name Wizard GPT
Plugin URI: https://strangerstudios.com/name-wizard/
Description: Name Wizard GPT is a plugin that allows you to add a Membership Level Name Generator to your WordPress site.
Version: 0.1.0
Requires at least: 6.0
Requires PHP: 7.4
Author: Stranger Studios
Author URI: https://strangerstudios.com/
License: GPLv2 or later
Text Domain: name-wizard-gpt
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

// Add shortcode to embed the form.
add_shortcode( 'name-wizard-gpt', 'name_wizard_gpt_shortcode' );
function name_wizard_gpt_shortcode( $atts ) {
    // Capture the output here to place into a variable.
    ob_start();
    
    ?>
    <div id="name-wizard-gpt">
        <?php if ( ! empty( $_REQUEST['name_wizard_submit'] ) ) { ?>
            <?php
            // Throw an error if the nonce is invalid.
            if ( ! isset( $_POST['name_wizard_gpt_nonce'] ) || ! wp_verify_nonce( $_POST['name_wizard_gpt_nonce'], 'name_wizard_gpt' ) ) {
                echo '<p>Sorry, your nonce was not correct. Please try again.</p>';
                exit;
            }

            // Get the number of levels.
            $number_levels = absint( $_POST['name_wizard_number_levels'] );
            $site_type = sanitize_text_field( $_POST['name_wizard_site_type'] );
            $goals = sanitize_text_field( $_POST['name_wizard_goal'] );

            ?>
            <p>Passing off your request to NameGPT. You mentioned having <?php echo intval( $number_levels );?> level(s) and that your site is about: <?php echo esc_html( $site_type );?>. You mentioned the following about your goals and location: <?php echo esc_html( $goals );?></p>
            <p>Please wait...</p>
            <hr />
            <?php

            // Build the prompt.
            $prompt = "Recommend level names for different types of membership sites based on content vertical, tiered/hierarchical levels, price/payment term structured levels, user/member-type levels, content delivery/subscription type levels, and sponsorship level/benefactor tiers. Consider clarity, creativity, and how the level names read within system phrases. Example level name ideas provided for each type of membership site structure. I have " . $number_levels . " level(s). My site is about " . $site_type . ". Our goals are " . $goals . ". Go!";

            // Ask the GPT API for a response.
            $output = name_wizard_ask_gpt( $prompt );

            // Display the response.
            echo wpautop( esc_html( $output ) );
            ?>
            <hr />
            <p>Want to try again? <a href="<?php echo esc_url( get_permalink() ); ?>">Click here</a>.</p>
        <?php } else { ?>
            <p>We're goign to ask the great NameGPT to help you come up with names for your membership levels.</p>
            
            <form method="post">
                <?php wp_nonce_field( 'name_wizard_gpt', 'name_wizard_gpt_nonce' ); ?>
                <p>
                <label for="name_wizard_number_levels">
                    How many levels are on your site?<br />
                    <input type="number" name="name_wizard_number_levels" id="name_wizard_number_levels" value="" placeholder="3" />
                </label>
                </p>

                <p>
                    <label for="name_wizard_site_type">
                    What is your membership site about?<br />
                        <input type="text" name="name_wizard_site_type" id="name_wizard_site_type" value="" placeholder="e.g. fitness" />
                    </label>
                </p>

                <p>
                    A great approach to level naming is to pull ideas from the goals of your organization, your location, the group or cause you represent. Tell us about your organization's location and goals.<br />
                    <label for="name_wizard_goal">
                        <input type="text" name="name_wizard_goal" id="name_wizard_goal" value="" placeholder="e.g. I want to help people learn about X" />
                    </label>
                </p>

                <input type="submit" name="name_wizard_submit" value="Submit" />
            </form>
        <?php } ?>
        
    </div>
    <?php

    // Capture the output and close the buffer.
    $content = ob_get_contents();
    ob_end_clean();

    return $content;
}

/**
 * Send a prompt to the GPT API
 * and get a response.
 * @param string $prompt The prompt to send.
 * @return string The response from the API.
 */
function name_wizard_ask_gpt( $prompt ) {
    // Make sure the API KEY is set.
    if ( ! defined( 'NAME_WIZARD_OPENAI_API_KEY' ) ) {
        return "ERROR: Please define NAME_WIZARD_OPENAI_API_KEY in your wp-config.php file.";
    }
    
    $url = "https://api.openai.com/v1/completions";
    $apikey = NAME_WIZARD_OPENAI_API_KEY; // Define in your wp-config.php.

    // Build the request headers.
    $headers = array(
        'Authorization' => 'Bearer ' . $apikey,
        'Content-Type' => 'application/json',
    );

    // Build the request body.
    $body = array(
        'model' => 'text-davinci-003',
        'prompt' => $prompt,
        'max_tokens' => 300,
        'temperature' => 0.9,
    );

    // Make the request.
    $args = array(
        'headers' => $headers,
        'body' => json_encode( $body ),
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
    );
    $response = wp_remote_post( $url, $args );

    // Check for errors.
    if ( is_wp_error( $response ) ) {
        $error = $response->get_error_message();
        return "ERROR:" . $error;
    } elseif ( 200 != $response['response']['code'] ) {
        $error = json_decode( $response['body'] );
        $error = $error->error->message;
        return "ERROR:" . $error;
    }

    // Decode the response.
    $response_body = json_decode( $response['body'] );
    $choice_text = $response_body->choices[0]->text;

    return $choice_text;
}

