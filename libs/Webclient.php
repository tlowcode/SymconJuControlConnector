<?php

class WebClient
{
    private $ch;
    private $cookie = '';

    public function Navigate($url, $post = array()) 
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_COOKIE, $this->cookie);
        if (!empty($post)) {
            curl_setopt($this->ch, CURLOPT_POST, TRUE);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
        }
        $response = $this->exec();
        if ($response['Code'] !== 200) {
            return FALSE;
        }
        //echo curl_getinfo($this->ch, CURLINFO_HEADER_OUT);
        return $response['Html'];
    }


    public function __construct()
    {
        $this->init();
    }

    public function __destruct()
    {
        $this->close();
    }

    private function init() 
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/6.0 (Windows NT 6.2; WOW64; rv:16.0.1) Gecko/20121011 Firefox/16.0.1");
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($this->ch, CURLOPT_HEADER, TRUE);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
    }

    private function exec(): array
    {
        $headers = array();
        $html = '';
        $separator = "\r\n\r\n";

        $output = curl_exec($this->ch);

        $retcode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        if ($retcode === 200) {

            $separatorpos = strpos($output, $separator);

            $html = substr($output, $separatorpos + strlen($separator));

            $h = trim(substr($output,0,$separatorpos));
            $lines = explode("\n", $h);
            foreach($lines as $line) {
                $kv = explode(':',$line);

                if (count($kv) === 2) {
                    $k = trim($kv[0]);
                    $v = trim($kv[1]);
                    $headers[$k] = $v;
                }
            }
        }

        // TODO: it would deserve to be tested extensively.
        if (!empty($headers['Set-Cookie']))
            $this->cookie = $headers['Set-Cookie'];

        return array('Code' => $retcode, 'Headers' => $headers, 'Html' => $html);
    }

    private function close()
    {
        curl_close($this->ch);
    }

}