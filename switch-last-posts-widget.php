<?php

/*
Plugin Name: Switch Last Posts Widget
Description: Thiw widget displays your recent posts according the current main category. Based on the "Recent Posts" widget.
Author: LordPretender
Version: 1.1
Author URI: http://www.duy-pham.fr
Domain Path: /languages
*/

//http://www.fruityfred.com/2012/08/20/internationaliser-traduire-un-plugin-wordpress/
load_plugin_textdomain('switch-last-posts-widget', false, dirname( plugin_basename( __FILE__ ) ). '/languages/');

//Déclaration de notre extention en tant que Widget
function register_SLPW_Widget() {
    register_widget( 'SLPW_Widget' );
}
add_action( 'widgets_init', 'register_SLPW_Widget' );

/**
* Documentation : http://codex.wordpress.org/Widgets_API
* S'inspirer de wp-includes/default-widgets.php (WP_Widget_Recent_Posts)
*/
class SLPW_Widget extends WP_Widget {
	private $catégories;
	
	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'slpw_widget', // Base ID
			'Switch Last Posts Widget', // Name
			array( 'description' => __('This widget displays your recent posts according the current root category.', 'switch-last-posts-widget'), ) // Args
		);
		
		$this->catégories = array();
	}
	
	/**
	* Cas où nous sommes à la page d'accueil : on est à la racine, donc on ajoute directement l'ID de la catégorie racine : 0.
	* Cas où nous sommes dans une catégorie : on récupère tous les parents de celle-ci.
	* Cas où nous sommes dans une page : on récupère toutes les catégories associées à la page puis on récupère tous les parents de ces dernières.
	*/
	private function getCatégories(){
		if(is_category()){ //Nous sommes dans une catégorie
			
			//Lecture de l'ID de la catégorie en cours
			$courant = get_query_var('cat');
			
			//On ajoute la catégorie actuelle et son parent dans le tableau
			$this->catégories[] = $courant;
			
			//On y ajoute les parents de la catégorie actuelle
			$this->getCatégorieRacine($courant);
			
		}elseif (is_front_page() || is_page() || is_author() || is_tag() || is_search() || is_attachment()){ //Nous sommes sur la page d'accueil
			
			$this->catégories[] = 0;
			
		}else{ //Nous sommes dans un article
			
			//Lecture de toutes les catégories pour l'article en cours.
			foreach(get_the_category() as $categorie){
				//Lecture des IDs
				$courant = $categorie->cat_ID;
				$parent = $categorie->category_parent;
				
				//On ajoute la catégorie actuelle et son parent dans le tableau
				$this->catégories[] = $courant;
				$this->catégories[] = $parent;
				
				//On y ajoute les parents du parent (sauf si déjà fait)
				if(!in_array($parent, $this->catégories))$this->getCatégorieRacine($parent);
			}
			
		}
	}
	
	/**
	* A partir d'une catégorie, on remplit le tableau de l'ID des catégories parentes de celle en cours.
	* @param int $parentID ID d'une catégorie
	*/
	private function getCatégorieRacine($parentID){
		while($parentID > 0){
			//Lecture des infos de la catégorie fournie
			$parent = get_category($parentID);
			
			//On récupère l'ID de son parent.
			$parentID = $parent->category_parent;
			$this->catégories[] = $parentID;
		}
	}
	
	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		//Chargement du tableau qui contiendra toutes les catégories parentes
		$this->getCatégories();
		
		//On récupère les ID des catégories paramétrées.
		$mainCat = $instance['mainCat'];
		$secondCat = $instance['secondCat'];
		
		//Si notre article (ou catégorie) en cours dépend (directement ou non) de la catégorie principale, on chargera donc la catégorie secondaire. Sinon, on charge la catégorie principale.
		$categorieToUse = count($this->catégories) > 0 && !in_array($secondCat, $this->catégories) ? $secondCat : $mainCat;
		
		//A laisser, semble servir pour ajouter les styles au code que nous aurons généré.
		ob_start();
		extract($args);

		$title = apply_filters('widget_title', empty($instance['title']) ? __('Recent Posts') : $instance['title'], $instance, $this->id_base);
		if ( empty( $instance['number'] ) || ! $number = absint( $instance['number'] ) )$number = 10;
		$show_date = isset( $instance['show_date'] ) ? $instance['show_date'] : false;
		
		$r = new WP_Query( apply_filters( 'widget_posts_args', array( 'posts_per_page' => $number, 'no_found_rows' => true, 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'cat' => $categorieToUse ) ) );
		if ($r->have_posts()) :
?>
		<?php echo $before_widget; ?>
		<?php if ( $title ) echo $before_title . $title . $after_title; ?>
		<ul>
		<?php while ( $r->have_posts() ) : $r->the_post(); ?>
			<li>
				<a href="<?php the_permalink() ?>" title="<?php echo esc_attr( get_the_title() ? get_the_title() : get_the_ID() ); ?>"><?php if ( get_the_title() ) the_title(); else the_ID(); ?></a>
			<?php if ( $show_date ) : ?>
				<span class="post-date"><?php echo get_the_date(); ?></span>
			<?php endif; ?>
			</li>
		<?php endwhile; ?>
		</ul>
		<?php echo $after_widget; ?>
<?php
		// Reset the global $the_post as this query will have stomped on it
		wp_reset_postdata();

		endif;
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
 	public function form( $instance ) {
		$title     	= isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number    	= isset( $instance['number'] ) ? absint( $instance['number'] ) : 5;
		$show_date 	= isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : false;
		$mainCat	= isset( $instance['mainCat'] ) ? absint( $instance['mainCat'] ) : 0;
		$secondCat = isset( $instance['secondCat'] ) ? absint( $instance['secondCat'] ) : 0;
		
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id( 'mainCat' ); ?>"><?php _e( 'Main category ID : ', 'switch-last-posts-widget' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'mainCat' ); ?>" name="<?php echo $this->get_field_name( 'mainCat' ); ?>" type="text" value="<?php echo $mainCat; ?>" size="3" /></p>

		<p><label for="<?php echo $this->get_field_id( 'secondCat' ); ?>"><?php _e( 'Secondary category ID : ', 'switch-last-posts-widget' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'secondCat' ); ?>" name="<?php echo $this->get_field_name( 'secondCat' ); ?>" type="text" value="<?php echo $secondCat; ?>" size="3" /></p>

		<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:' ); ?></label>
		<input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

		<p><input class="checkbox" type="checkbox" <?php checked( $show_date ); ?> id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" />
		<label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Display post date?' ); ?></label></p>
<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['mainCat'] = (int) $new_instance['mainCat'];
		$instance['secondCat'] = (int) $new_instance['secondCat'];
		$instance['show_date'] = (bool) $new_instance['show_date'];

		return $instance;
	}
}

?>