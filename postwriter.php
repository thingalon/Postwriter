<?php
/*
Plugin Name: Postwriter
Plugin URI: http://thingalon.com/
Description: Ghostwrites new posts automatically, based on your past posts. Useful only for comedy value.
Version: 0.1.0
Author: thingalon
Author URI: http://thingalon.com
License: GPLv2 or later
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
	exit;
}

//	Load javascript / enqueue hook for new post submit panel
function postwriter_enqueue_admin_scripts( $hook ) {
	if ( 'post-new.php' == $hook ) {
		wp_register_script( 'postwriter_admin_script', plugins_url( '/admin.js', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-slider' ) );
		wp_enqueue_script( 'postwriter_admin_script' );

		wp_register_style( 'postwriter_jquery_ui', plugins_url( 'postwriter/jquery-ui.min.css', __FILE ) );
		wp_enqueue_style( 'postwriter_jquery_ui' );
	}
}
add_action( 'admin_enqueue_scripts', 'postwriter_enqueue_admin_scripts' );

function postwriter_add_custom_box() {
	add_meta_box( 'postwriter_metabox', __( 'Postwriter - Generate Random', 'postwriter' ), 'postwriter_populate_custom_box', 'post', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'postwriter_add_custom_box' );

function postwriter_populate_custom_box() {
	?>
		<div class="misc-pub-section">
			<div>
				Chain by: 
				<select id="postwriter_chain_by">
					<option value="character">Character</option>
					<option value="word">Word (less random)</option>
				</select>
			</div>
			<div style="margin:10px 0px;">
				Chain length:
				<div style="display:inline-block;width:50%;margin-left:10px;position:relative;text-align:center;">
					<div id="postwriter_chain_size"></div>
					<span id="postwriter_chain_size_description"></span>
				</div>
			</div>
			<div style="float:right">
				<span id="postwriter_generate">
					<a href="#" class="button button-small">Generate Post</a>
				</span>
				<span id="postwriter_loading" style="display:none;">
					<img src="images/wpspin_light.gif" alt="generating..." >
					Generating...
				</span>
			</div>
			<div style="clear:both;"></div>
		</div>
	<?php
}

//	AJAX hook to generate and return a new post body.
function postwriter_generate() {
	//	Check parameters
	$body_markov_order = isset( $_POST['chain_size'] ) ? (int)$_POST['chain_size'] : 4;
	$body_tokenizer = ( isset( $_POST['chain_by'] ) && 'word' == $_POST['chain_by'] ) ? 'Postwriter_Word_Tokenizer' : 'Postwriter_Character_Tokenizer';
	
	if ( $body_markov_order < 1 )
		$body_markov_order = 1;
	elseif ( $body_markov_order > 8 )
		$body_markov_order = 8;

	$body_length = ( 'Postwriter_Word_Tokenizer' == $body_tokenizer ) ? rand( 80, 500 ) : rand( 500, 1000 );
	
	//	Fetch 100 random posts to use as input
	$random_posts = get_posts( array( 'posts_per_page' => 100, 'orderby' => 'rand' ) );
	if ( count( $random_posts ) < 10 ) {
		//	Don't bother with less than 10 posts to work with.
		echo json_encode( array( 'error' => 'You need at least 10 posts to meaningfully generate new posts. You seem to have only ' . count( $random_posts ) . '.' ) );
		die();
	}
	
	//	Create markov chainers with randomized properties	
	$title_markov = new Postwriter_Markov_Chainer( 'Postwriter_Character_Tokenizer', rand( 3, 5 ) );
	$body_markov = new Postwriter_Markov_Chainer( $body_tokenizer, $body_markov_order );
	
	//	Feed the found posts to the markov chainers
	foreach ( $random_posts as $post ) {
		$title_markov->feed_data( postwriter_strip_post( $post->post_title ) );
		$body_markov->feed_data( postwriter_strip_post( $post->post_content ) );
	}
	
	//	Generate new post body/title
	$new_title = $title_markov->generate_data( rand( 20, 60 ), true );
	$new_body = $body_markov->generate_data( $body_length );
	
	echo json_encode( array( 'title' => $new_title, 'body' => $new_body ) );
	die();
}
add_action('wp_ajax_postwriter_generate', 'postwriter_generate');

//	Strip the stuff we don't want to cope with out of source posts.
function postwriter_strip_post( $post ) {
	$post = preg_replace( '[\(\)|"]', '', $post );
	$post = trim( preg_replace( "/<pre[^>]*>.*?<\/pre>/s", "", $post ) );
	$post = strip_tags( $post );
	$post = strip_shortcodes( $post );
	return $post;
}

//	Base tokenizer class; contains behavior to generate a bunch of tokens, needs to be overridden
class Postwriter_Tokenizer {
	public $string;
	public $cursor;

	function Postwriter_Tokenizer( $string = null ) {
		$this->string = $string;
		$this->cursor = 0;
	}
	
	function get_n_symbols( $n ) {
		$r = array();
		for ( $i = 0; $i < $n; ++$i ) {
			$s = $this->get_next_symbol();
			if ( $s === false )
				return $r;
				
			$r []= $s;
		}
		
		return $r;
	}
	
	function join_symbols( $symbols ) {
		return implode( ' ', $symbols );
	}
};

//	Word-by-word tokenizer. Splits out words and interesting symbols into tokens of their own.
class Postwriter_Word_Tokenizer extends Postwriter_Tokenizer {
	function get_next_symbol() {
		if ( $this->cursor >= strlen( $this->string ) )
			return false;	
	
		$sym = '';
		
		while ( $this->cursor < strlen( $this->string ) && ( ctype_alpha( $this->string{$this->cursor} ) || ( $this->string{$this->cursor} == "'" && count( $sym ) ) ) )
			$sym .= $this->string{$this->cursor++};
		
		if ( empty( $sym ) ) {
			while ( $this->cursor < strlen( $this->string ) && ctype_digit( $this->string{$this->cursor} ) )
				$sym .= $this->string{$this->cursor++};
			
			if ( $this->cursor < strlen( $this->string ) && empty( $sym ) )
				$sym .= $this->string{$this->cursor++};
		}

		while ( $this->cursor < strlen( $this->string ) && ctype_space( $this->string{$this->cursor} ) )
			$this->cursor++;
		
		return $sym;
	}
	
	function join_symbols( $symbols ) {
		$string = implode( ' ', $symbols );
					
		//	Tidy it up a bit. 
		$string = preg_replace( "#(\d) \. (\d)#", '$1.$2', $string );	//	Decimal place.
		$string = preg_replace( "# ([.:,;!?]) #", "$1 ", $string );	//	Full stops, commas, etc.
		$string = preg_replace( "# _ #", "_", $string );	//	Underscores
		
		return $string;
	}
};

//	Character-by-character tokenizer. Blindly splits the input into individual characters.
class Postwriter_Character_Tokenizer extends Postwriter_Tokenizer {
	function get_next_symbol() {
		if ( $this->cursor >= strlen( $this->string ) )
			return false;
		
		return $this->string{$this->cursor++};
	}
	
	function join_symbols( $symbols ) {
		return implode( '', $symbols );
	}
};

//	Markov chainer: where the magic happens. Takes any textual input, and generates similar output.
class Postwriter_Markov_Chainer {
	private $table;
	private $order;
	private $tokenizer_class;
	
	function Postwriter_Markov_Chainer( $tokenizer_class, $order ) {
		error_log( $tokenizer_class );

		$this->tokenizer_class = $tokenizer_class;
		$this->order = $order;
		$this->table = array();
	}
	
	//	Push data into the markov chainer, using the configured tokenizer
	function feed_data( $input ) {
		$tokenizer = new $this->tokenizer_class( $input );
		$from = array();
		$to = $tokenizer->get_n_symbols( $this->order );
		while ( count( $to ) > 0 ) {
			$from_key = serialize( $from );
			$to_key = serialize( $to );
			
			if ( ! isset( $this->table[ $from_key ] ) )
				$this->table[ $from_key ] = array();
			if ( ! isset( $this->table[ $from_key ][ $to_key ] ) )
				$this->table[ $from_key ][ $to_key ] = 1;
			else
				$this->table[ $from_key ][ $to_key ]++;
			
			$from = $to;
			$to = $tokenizer->get_n_symbols( $this->order );
		}
	}
	
	//	Generate $length tokens of new data.
	function generate_data( $length, $end_on_hiccup = false ) {
		$key = serialize( array() );
		$data = array();
		
		while ( count( $data ) < $length ) {
			$next_key = null;
			if ( isset( $this->table[ $key ] ) )
				$next_key = $this->weighted_lookup( $this->table[ $key ] );

			if ( $next_key ) {
				$data = array_merge( $data, unserialize( $next_key ) );
			} else {
				if ( $end_on_hiccup )
					break;
				
				$next_key = serialize( array() );
			}
			
			$key = $next_key;
		}
		
		$tok = new $this->tokenizer_class();
		return $tok->join_symbols( $data );
	}
	
	//	Helper method to choose the next set of tokens, by weighted roulette.
	function weighted_lookup( $array ) {
		$total = array_sum( $array );
		$rand = mt_rand( 1, $total );
		foreach ( $array as $item => $weight ) {
			if ( $rand <= $weight )
				return $item;
			$rand -= $weight;
		}
	}
};
