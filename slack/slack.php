<?php

require_once(INCLUDE_DIR.'class.signal.php');
require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');

class SlackPlugin extends Plugin
{
	var $config_class = "SlackPluginConfig";
	
	function bootstrap()
	{
		//Signal::connect('model.created', array($this, 'onTicketCreated'), 'Ticket');
		Signal::connect('model.created', array($this, 'onThreadEntryCreated'), 'ThreadEntry');
	}

	function onThreadEntryCreated($entry)
	{
		if ($entry->ht['thread_type'] == 'R') {
			// Responses by staff
			$this->onResponseCreated($entry);
		} elseif ($entry->ht['thread_type'] == 'N') {
			// Notes by staff or system
			$this->onNoteCreated($entry);
		} else {
			// New tickets or responses by users
			$this->onMessageCreated($entry);
		}
	}

	//Responses by staff - see onThreadEntryCreated()
	function onResponseCreated($response)
	{
		$this->sendThreadEntryToSlack($response, 'Response', 'warning');
	}

	//Internal Notes by staff - see onThreadEntryCreated()
	function onNoteCreated($note)
	{
		$this->sendThreadEntryToSlack($note, 'Note', 'good');
	}

	//New tickets or comments by users - see onThreadEntryCreated()
	function onMessageCreated($message)
	{
		$this->sendThreadEntryToSlack($message, 'Message', 'danger');
	}
	
	function sendThreadEntryToSlack($entry, $label, $color)
	{
		global $ost;

		$ticketLink = $ost->getConfig()->getUrl().'scp/tickets.php?id='.$entry->getTicket()->getId();

		$title = $entry->getTitle() ?: $label;
		$body = $entry->getBody() ?: $entry->ht['body'] ?: 'No content';

		$dept = $entry->getTicket()->getDept();
		if ($dept instanceof Dept) {
			$id = $dept->getId();
			$channel = self::getConfig()->get("slack_department_id_".$id);
		}

		$this->sendToSlack(
			array(
				'attachments' => array(
					array(
						'pretext' => $label.' by '.$entry->getPoster(),
						'fallback' => 'New '.$label.' in <'.$ticketLink.'> by '.$entry->getPoster(),
						'title' => 'Ticket '.$entry->getTicket()->getNumber().': '.$title,
						'title_link' => $ticketLink,
						'color' => $color,
						'text' => $this->escapeText($body)
					),
				),
				'channel' => $channel
			)
		);
	}
	
	function onTicketCreated($ticket)
	{
		global $ost;

		$ticketLink = $ost->getConfig()->getUrl().'scp/tickets.php?id='.$ticket->getId();

		$title = $ticket->getSubject() ?: 'No subject';
		$body = $ticket->getLastMessage()->getMessage() ?: 'No content';

		$this->sendToSlack(
			array(
				'attachments' => array(
					array(
						'pretext' => 'by '.$ticket->getName().' ('.$ticket->getEmail().')',
						'fallback' => 'New Ticket <'.$ticketLink.'> by '.$ticket->getName().' ('.$ticket->getEmail().')',
						'title' => 'Ticket '.$ticket->getNumber().': '.$title,
						'title_link' => $ticketLink,
						'color' => "danger",
						'text' => $this->escapeText($body)
					),
				),
			)
		);
	}
	
	function sendToSlack($payload)
	{
		try {
			global $ost;
	
			$data_string = utf8_encode(json_encode($payload));
			$url = $this->getConfig()->get('slack-webhook-url');
			
			if (!function_exists('curl_init')){
				error_log('osticket slackplugin error: cURL is not installed!');
			}
			$ch = curl_init($url);
			
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($data_string)
				)
			);
	
			if (curl_exec($ch) === false) {
				throw new Exception($url . ' - ' . curl_error($ch));
			} else {
				$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
				if ($statusCode != '200') {
					throw new Exception($url . ' Http code: ' . $statusCode);
				}
			}

			curl_close($ch);
		} catch(Exception $e) {
			error_log('Error posting to Slack. '. $e->getMessage());
		}
	}

	function escapeText($text)
	{
		$text = str_replace('<br />', ' ', $text);
		$text = strip_tags($text);
		$text = str_replace('&', '&amp;', $text);
		$text = str_replace('<', '&lt;', $text);
		$text = str_replace('>', '&gt;', $text);

		return $text;
	}
}
