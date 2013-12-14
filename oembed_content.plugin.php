<?php
class OEmbedContent extends Plugin
{
	/**
	 * Register the template.
	 **/
	function action_init()
	{
		// $this->load_text_domain( 'simpledashblock' );
		$this->add_template( 'block.add_oembed', dirname(__FILE__) . '/block.add_oembed.php' );
	}
	
	public function action_plugin_activation( $plugin_file )
	{
		Post::add_new_type( 'oembed' );
	}

	public function action_plugin_deactivation( $plugin_file )
	{
		Post::deactivate_post_type( 'oembed' );
	}
	
	/**
	* Create name string. This is where you make what it displays pretty.
	**/
	public function filter_post_type_display($type, $foruse)
	{ 
		$names = array( 
			'oembed' => array(
				'singular' => _t('Embedded content', __CLASS__),
				'plural' => _t('Embedded content', __CLASS__),
			)
		); 
		return isset($names[$type][$foruse]) ? $names[$type][$foruse] : $type; 
	}
	
	/**
	 * Add to the list of possible block types.
	 **/
	public function filter_block_list($block_list)
	{
		$block_list['add_oembed'] = _t('OEmbed Content', __CLASS__);
		return $block_list;
	}
	
	/**
	 * Return a list of blocks that can be used for the dashboard
	 * @param array $block_list An array of block names, indexed by unique string identifiers
	 * @return array The altered array
	 */
	public function filter_dashboard_block_list($block_list)
	{
		$block_list['add_oembed'] = _t('OEmbed content', __CLASS__);
		return $block_list;
	}
	
	public function action_block_content_add_oembed($block, $theme)
	{
		$form = new FormUI(__CLASS__);
		$form->append('text', 'webpage_url', 'null:null', _t('Web page URL:', __CLASS__));
		$form->append('submit', 'submit', _t('Submit'));
		$form->on_success(array($this, 'create_post'));
		$block->form = $form;
	}
	
	public function action_form_publish( $form, $post )
	{
		if( $form->content_type->value == Post::type( 'oembed' ) ) {
			$source = $form->insert('content', 'text', 'webpage_url', 'null:null', 'Source URL');
			$source->value = (isset($post->info->webpage_url)) ? $post->info->webpage_url : '';
			$form->webpage_url->template = "admincontrol_text";
			if(isset($post->content) && !empty($post->content)) {
				$preview = '<div class="container transparent">' . $post->content . '</div>';
			}
			else {
				$preview = '';
			}
			$form->append('static', 'content', $preview);
		}
	}
	
	public function discover($url)
	{
		$page = RemoteRequest::get_contents($url);
		$dom = new DOMDocument();
		@$dom->loadHTML($page);
		$links = $dom->getElementsByTagName("link");
		foreach($links as $link) {
			if($link->getAttribute("type") == "application/json+oembed") {
				$source = $link->getAttribute("href");
				break;
			}
		}
		if(isset($source)) {
			$json = RemoteRequest::get_contents($source);
			$info = json_decode($json);
			return $info;
		}
		else {
			Session::error(_t("No embeddable content found", __CLASS__));
			return false;
		}
	}
	
	public function create_post($form)
	{
		$url = $form->webpage_url->value;
		if(!isset($url) || empty($url)) return true; // true = yes, an error has occured
		
		$content = $this->discover($url);
		if(!isset($content) || empty($content)) return true;
		
		$post = new Post();
		$post->content_type = Post::type('oembed');
		$post->user_id = User::identify()->id;
		$post->content = $content->html;
		$post->title = $content->title;
		$post->insert();
		$post->info->webpage_url = $url;
		$post->publish();
		
		Session::notice(_t("Post published successfully", __CLASS__));
		
		return false;
	}
	
	/**
	 * Save our data to the database on post publish form submit
	 */
	public function action_publish_post( $post, $form )
	{
		if ($post->content_type == Post::type('oembed')) {
			$post->info->webpage_url = $form->webpage_url->value;
			$post->content = $this->discover($form->webpage_url->value)->html;
		}
	}
	
	/**
	 * Add embedded content to the output (0.9 method)
	 */
	/*public function filter_template_user_filters( $filters ) 
	{
		// Cater for the home page which uses presets as of d918a831
		if ( isset( $filters['preset'] ) ) {
			if(isset($filters['content_type'])) {
				$filters['content_type'] = Utils::single_array( $filters['content_type'] );
			}
			$filters['content_type'][] = Post::type( 'photo' );
			$filters['content_type'][] = Post::type( 'entry' );
		} else {		
			// Cater for other pages like /page/1 which don't use presets yet
			if ( isset( $filters['content_type'] ) ) {
				$filters['content_type'] = Utils::single_array( $filters['content_type'] );
				$filters['content_type'][] = Post::type( 'photo' );
			}
		}
		return $filters;
	}*/
}
?>