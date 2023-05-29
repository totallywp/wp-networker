<?php

class WP_Networker_Updater {

	private $slug;
	private $pluginData;
	private $username;
	private $repo;
	private $remoteVersion;

	public function __construct($slug, $username, $repo) {
		$this->slug = $slug;
		$this->username = $username;
		$this->repo = $repo;
		$this->remoteVersion = $this->getRemote('tag_name');

		add_filter('pre_set_site_transient_update_plugins', array($this, 'setTransient'));
		add_filter('plugins_api', array($this, 'setPluginInfo'), 20, 3);
	}

	private function initPluginData() {
		$this->pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->slug);
	}

	private function getRemote($field) {
		$response = wp_remote_get("https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest");

		if(!is_wp_error($response)) {
			$response = json_decode($response['body']);

			if(is_object($response)) {
				return $response->$field;
			}
		}

		return false;
	}

	public function setTransient($transient) {
		if(empty($transient->checked)) {
			return $transient;
		}

		$this->initPluginData();

		if(version_compare($this->pluginData["Version"], $this->remoteVersion, '<')) {
			$obj = new stdClass();
			$obj->slug = $this->slug;
			$obj->new_version = $this->remoteVersion;
			$obj->url = $this->pluginData["PluginURI"];
			$obj->package = $this->getRemote('zipball_url');
			$transient->response[$this->slug . '/' . $this->slug . '.php'] = $obj;
		}

		return $transient;
	}

	public function setPluginInfo($false, $action, $response) {
		$this->initPluginData();
		$this->remoteVersion = $this->getRemote('tag_name');

		if($response->slug === $this->slug) {
			if($this->remoteVersion) {
				$response->last_updated = $this->getRemote('published_at');
				$response->sections = array(
					'description' => $this->pluginData["Description"],
				);
				$response->version = $this->remoteVersion;
				$response->download_link = $this->getRemote('zipball_url');
			}
		}

		return $response;
	}
}