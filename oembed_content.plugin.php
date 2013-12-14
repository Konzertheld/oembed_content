<?php
class OEmbedContent extends Plugin
{
	public function action_plugin_activation( $plugin_file )
	{
		Post::add_new_type( 'oembed' );
	}

	public function action_plugin_deactivation( $plugin_file )
	{
		Post::deactivate_post_type( 'oembed' );
	}
	
	public function action_form_publish( $form, $post )
	{
		if( $form->content_type->value == Post::type( 'oembed' ) ) {
			$source = $form->insert('content', 'text', 'webpage_url', 'null:null', 'Source URL');
			$source->value = (isset($post->info->webpage_url)) ? $post->info->webpage_url : '';
			$form->webpage_url->template = "admincontrol_text";
			if(isset($post->info->webpage_url) && !empty($post->info->webpage_url)) {
				$preview = '<div class="container transparent">' . $this->discover($post->info->webpage_url) . '</div>';
			}
			else {
				$preview = '';
			}
			$form->insert('content', 'static', 'preview', $preview);

			// Re-create the Content field
			//$form->content->remove();
			$content = $form->append( 'hidden', 'content', 'null:null' );
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
		$json = RemoteRequest::get_contents($source);
		$info = json_decode($json);
		return $info->html;
	}
	
	/**
	 * Save our data to the database on post publish form submit
	 */
	public function action_publish_post( $post, $form )
	{
		if ($post->content_type == Post::type('oembed')) {
			$post->info->webpage_url = $form->webpage_url->value;
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