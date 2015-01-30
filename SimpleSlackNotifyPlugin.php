<?php

namespace ECH\PhpCIPlugins;


use PHPCI\Builder;
use PHPCI\Model\Build;

/**
 * Simple Slack Plugin
 * @author       Tomáš Strejček <tomas.strejcek@ghn.cz>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class SimpleSlackNotifyPlugin implements \PHPCI\Plugin
{
    private $webHook;
    private $channel;
    private $username;
    private $message;
    private $icon;

    /**
     * Set up the plugin, configure options, etc.
     * @param Builder $phpci
     * @param Build $build
     * @param array $options
     * @throws \Exception
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $this->phpci = $phpci;
        $this->build = $build;

        if (!is_array($options) || !isset($options['webhook'])) {

        }
        $this->webHook = trim($options['webhook']);

        if (!is_array($options) || !isset($options['room'])) {
            throw new \Exception('Please define the webhook_url for slack_notify plugin!');
        }
        $this->channel = $options['channel'];

        if (isset($options['message'])) {
            $this->message = $options['message'];
        } else {
            $this->message = '<%PROJECT_URI%|%PROJECT_TITLE%> - <%BUILD_URI%|Build #%BUILD%> has finished ';
            $this->message .= 'for commit <%COMMIT_URI%|%SHORT_COMMIT% (%COMMIT_EMAIL%)> ';
            $this->message .= 'on branch <%BRANCH_URI%|%BRANCH%>';
        }


        if (isset($options['username'])) {
            $this->username = $options['username'];
        } else {
            $this->username = 'PHPCI';
        }

        if (isset($options['icon'])) {
            $this->icon = $options['icon'];
        }


    }

    /**
     * Run the Slack plugin.
     * @return bool
     */
    public function execute()
    {
        $message = $this->phpci->interpolate($this->message);

        $successfulBuild = $this->build->isSuccessful();

        $buildMsg = $this->build->getLog();

        $buildMsg = str_replace('[0;32m', '', $buildMsg);
        $buildMsg = str_replace('/[0m', '', $buildMsg);
        $buildMsg = str_replace('[0m', '', $buildMsg);

        $buildmessages = explode('RUNNING PLUGIN: ', $buildMsg);

        if ($successfulBuild) {
            $status = 'Success';
            $color = 'good';
        } else {
            $status = 'Failed';
            $color = 'danger';
        }

        // Build up the attachment data
        $fields = array(array(
            'title' => 'Status',
            'value' => $status,
            'short' => true
        ));

        foreach ($buildmessages as $bm) {

            $pos = mb_strpos($bm, "\n");
            $firstRow = mb_substr($bm, 0, $pos);

            //skip long outputs
            if ($firstRow == 'slack_notify') continue;
            if ($firstRow == 'php_loc') continue;

            $fields[] = array(
                'title' => 'RUNNING PLUGIN: ' . $firstRow,
                'value' => $firstRow == 'composer' ? '' : mb_substr($bm, $pos),
                'short' => false
            );

        }

        $attachment = array(
            'fallback' => $message,
            'title' => $message,
            'color' => $color,
            'fields' => $fields
        );

        $payload = array(
            'username' => $this->username,
            'icon_emoji' => $this->icon,
            'text' => ''
        );

        $success = true;
        try {

            $data = http_build_query(array(
                'payload' => json_encode(array_merge($payload, $attachment))
            ));
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $this->webHook);
            $result = curl_exec($ch);
            $chinfo = curl_getinfo($ch);
            if(!$result) {
                throw new \Exception(curl_error($ch), curl_errno($ch));
            }
            curl_close($ch);

        } catch (\Exception $e) {
            $this->phpci->log($e->getMessage());;
        }
        return $success;
    }
}
