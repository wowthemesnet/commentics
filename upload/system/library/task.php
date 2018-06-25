<?php
namespace Commentics;

class Task {
	private $db;
	private $comment;
	private $home;
	private $notify;
	private $setting;
	private $validation;

	public function __construct($registry) {
		$this->db = $registry->get('db');
		$this->comment = $registry->get('comment');
		$this->home = $registry->get('home');
		$this->notify = $registry->get('notify');
		$this->setting = $registry->get('setting');
		$this->validation = $registry->get('validation');

		if (!$this->db->isConnected() || !$this->db->isInstalled()) {
			return;
		}

		$last_task = $this->setting->get('last_task');

		$date = date('Y-m-d');

		/* Only run the tasks once a day */
		if ($last_task != $date) {
			$this->db->query("UPDATE `" . CMTX_DB_PREFIX . "settings` SET `value` = '" . $this->db->escape($date) . "' WHERE `title` = 'last_task'");

			if ($this->setting->get('task_enabled_delete_bans')) {
				$this->deleteBans();
			}

			if ($this->setting->get('task_enabled_delete_comments')) {
				$this->deleteComments();
			}

			if ($this->setting->get('task_enabled_delete_reporters')) {
				$this->deleteReporters();
			}

			if ($this->setting->get('task_enabled_delete_subscriptions')) {
				$this->deleteSubscriptions();
			}

			if ($this->setting->get('task_enabled_delete_voters')) {
				$this->deleteVoters();
			}

			if (!$this->setting->get('new_version_notified')) {
				$this->checkVersion();
			}
		}
	}

	private function deleteBans() {
		$this->db->query("DELETE FROM `" . CMTX_DB_PREFIX . "bans` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . (int)$this->setting->get('days_to_delete_bans') . " DAY)");
	}

	private function deleteComments() {
		$comments = $this->db->query("SELECT * FROM `" . CMTX_DB_PREFIX . "comments` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . (int)$this->setting->get('days_to_delete_comments') . " DAY)");

		foreach ($comments as $comment) {
			$this->comment->deleteComment($comment['id']);
		}
	}

	private function deleteReporters() {
		$this->db->query("DELETE FROM `" . CMTX_DB_PREFIX . "reporters` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . (int)$this->setting->get('days_to_delete_reporters') . " DAY)");
	}

	private function deleteSubscriptions() {
		$this->db->query("DELETE FROM `" . CMTX_DB_PREFIX . "subscriptions` WHERE `is_confirmed` = '0' AND `date_added` < DATE_SUB(NOW(), INTERVAL " . (int)$this->setting->get('days_to_delete_subscriptions') . " DAY)");
	}

	private function deleteVoters() {
		$this->db->query("DELETE FROM `" . CMTX_DB_PREFIX . "voters` WHERE `date_added` < DATE_SUB(NOW(), INTERVAL " . (int)$this->setting->get('days_to_delete_voters') . " DAY)");
	}

	private function checkVersion() {
		if (extension_loaded('curl') || (bool)ini_get('allow_url_fopen')) {
			$latest_version = $this->home->getLatestVersion();

			if ($this->validation->isFloat($latest_version)) {
				if (version_compare(CMTX_VERSION, $latest_version, '<')) {
					$this->notify->adminNotifyNewVersion(CMTX_VERSION, $latest_version);

					$this->db->query("UPDATE `" . CMTX_DB_PREFIX . "settings` SET `value` = '1' WHERE `title` = 'new_version_notified'");
				}
			}
		}
	}
}
?>