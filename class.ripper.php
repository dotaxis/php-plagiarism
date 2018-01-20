<?php

 /*------------------------------------------------------------------------------------*\
 |          Rip and Spin Class to Automate Content Posting for Wordpress Sites          |
 |______________________________________________________________________________________|
 |                                                                                      |
 |             Authors:    Chase Taylor                                                 |
 |             Created:    March 2015                                                   |
 |             Version:     0.21                                                        |
 |             License:    None                                                         |
 |             Supported Websites:                                                      |
 |                - ViralNova                                                           |
 |                - BuzzFeed                                                            |
 |             Note:                                                                    |
 |                This class requires PHP Simple DOM Parser 1.5                         |
 |                and PHP5. It will not work with PHP4 or PHP7.                         |
 \*------------------------------------------------------------------------------------*/

 /*    Usage:
 if (stripos($url), 'viralnova.com') {
     $Ripper = new ViralNova
 } else if (stripos($url, 'buzzfeed.com')) {
     $Ripper = new BuzzFeed;
 }
 $Ripper->Load($url);
 $Ripper->Strip();
 $Ripper->Save_Attributes();
 $Ripper->Rip_Images($post_id);
 // Get $post_id using class.postcontroller.php
 // or pass 0 which may fail
 $Ripper->Rebuild();
 $Ripper->Trim();
 $Ripper->Spin()
 $CONTENT = $Ripper->doc;

 $CONTENT will return rewritten page in HTML as a string/
 Post this to wordpress using class.postcontroller.php */


class Ripper {
    public $doc, $strippables = [];
    public $imgs = [ 'src' => [], 'bf_src' => [], 'alt' => [] ];
    public $links = [ 'href' => [], 'title' => [], 'text' => [] ];

        public function Strip() {
            if (!empty($strippables)) {
                foreach ($strippables as $tag) {
                    foreach($this->doc->find($tag) as $t) {
                        $t->outertext = '';
                    }
                }
            }
        }

        public function save_attrs($tag, $save, &$saved) {
            foreach($this->doc->find($tag) as $t) {
                if (isset($t->$save) && $t->$save !== '')
                    $saved[] = $t->$save;
                else
                    $saved[] = null;
            }
        }

        public function rebuild_embeds() {
            foreach ($this->doc->find('iframe') as $iframe) {
                if (stripos($iframe->src, 'youtube') || stripos($iframe->src, 'vimeo'))
                    $iframe->outertext = '[embed]' . $iframe->src . '[/embed]';
            }
        }

        public function rebuild_imgs($tag, $src_arr, $alt_arr, $post_id) {
            if (!function_exists('media_sideload_image')) {
                require_once('../wp-admin/includes/image.php');
                require_once('../wp-admin/includes/file.php');
                require_once('../wp-admin/includes/media.php');
            }
            if (count($src_arr) > 0) {
                for ($i = 0; $i < count($src_arr); $i++) {
                    if (filter_var($src_arr[$i], FILTER_VALIDATE_URL)) {
                        if (!empty($alt_arr[$i]))
                            $newimg = media_sideload_image($src_arr[$i], $post_id, $alt_arr[$i]);
                        else
                            $newimg = media_sideload_image($src_arr[$i], $post_id);

                        $this->doc->find($tag,$i)->outertext = '<br />' . $newimg . '<br />';
                    }
                }
            }
        }

        public function Spin() {
            $thesaurus = file_get_contents("thesaurus.txt");
            $library = preg_split('/\r?\n/', $thesaurus);
            $sep_arr = [' ', '.', ',', ';', ':', '?', '!', '"', '-'];


            for ($i=0; $i < count($library); $i++) {
                $searchlib = explode("|",$library[$i]);
                foreach ($sep_arr as $sep) {
                    for ($j=0; $j<count($searchlib); $j++) {
                        $searchword = $searchlib[$j];
                        if (stripos($this->doc, ' '.$searchword.$sep) !== false) {
                            $newlib = $searchlib;
                            unset ($newlib[$j]);
                            $newlib = array_merge($newlib);

                            $newword = $newlib[rand(0, count($newlib) -1)];
                            $allcap = strtoupper($searchword);
                            $onecap = ucfirst($searchword);

                            if (strpos($this->doc, $allcap.$sep) !== false) {
                                $searchword = $allcap;
                                $newword = strtoupper($newword);
                            } else if (strpos($this->doc, $onecap.$sep) !== false
                            && is_numeric($onecap[0]) === false) {
                                $searchword = $onecap;
                                $newword = ucfirst($newword);
                            } else {
                                $searchword = strtolower($searchword);
                                $newword = strtolower($newword);
                            }
                            /*echo "Search for:" . $searchword.$sep . "<br />";
                            echo "Array of Replacements: " . print_r($newlib) . "<br />" ;
                            echo "Chosen Replacement: " . $newword.$sep . "<br /><br />";*/
                            $this->doc = str_replace(' '.$searchword.$sep, ' '.$newword.$sep,$this->doc);
                            break;
                        }
                        break;
                    }
                }
            }
        }

}

class ViralNova extends Ripper {

        public function Load($url) {
            $this->doc = file_get_html($url, $use_include_path = false, $context=null, $offset = -1, $maxLen=-1, $lowercase = true, $forceTagsClosed=true, $target_charset = DEFAULT_TARGET_CHARSET, $stripRN=true, $defaultBRText=false);
            $strippables = ['script', 'style', 'a[title=facebook', 'a[title=twitter]', 'a[title=google+]', 'a[title=pinterest]', 'a[title=email]', 'a[class=social-share]', 'img[class!=articleimg]'];
            $firstp = $this->doc->find('p', 0)->innertext;
            $lastp = $this->doc->find('p', -1)->innertext;
            $this->doc = $this->doc->find('article div[class=content]')[0];    //narrow things down a bit
        }

        public function rebuild_links($href, $title, $text) {
            if (count($href) > 0) {
                for ($i = 0; $i < count($href); $i++) {
                    if (stripos($href[$i], 'viralnova.com/')) {
                        $this->doc->find('a',$i)->outertext = $this->doc->find('a',$i)->plaintext;
                    } else if ($href[$i] == "#" || $href[$i] == '' || $href[$i] == null) {
                        $this->doc->find('a',$i)->outertext = '';
                    } else if ($title[$i] !== null) {
                        $this->doc->find('a',$i)->outertext = '<a href="' . $href[$i] . '" title="' .  $title[$i] . '">' . $text[$i] . '</a>';
                    } else {
                        $this->doc->find('a',$i)->outertext = '<a href="' . $href[$i] . '">' . $text[$i] . '</a>';
                    }
                }
            }
        }

        public function rebuild_titles() {
            foreach ($this->doc->find('h3, h4, h5') as $h)
                $h->outertext = '<br /><br /><strong>' . $h->innertext . '</strong>';
            foreach ($this->doc->find('p') as $p)
                $p->outertext = '<br /><br />' . $p->innertext;
        }

        public function Save_Attributes() {
            $this->save_attrs('img[class=articleimg]', src, $this->imgs['src']);
            $this->save_attrs('img[class=articleimg]', alt, $this->imgs['alt']);
            $this->save_attrs('a', href, $this->links['href']);
            $this->save_attrs('a', title, $this->links['title']);
            $this->save_attrs('a', innertext, $this->links['text']);
        }

        public function Trim() {
            if ($firstp != '')
                $this->doc = strstr($this->doc, $firstp);
            if ($lastp != '')
                $this->doc = substr($this->doc, 0, strpos($this->doc, $lastp));

            $this->doc = strip_tags($this->doc, '<img><p><a><strong><br>');
            $this->doc = preg_replace('/\s+/', ' ', $this->doc);
        }

        public function Rip_Images($post_id) {
            $this->rebuild_imgs('img[class=articleimg]', $this->imgs['src'], $this->imgs['alt'], $post_id);
        }

        public function Rebuild() {
            $this->rebuild_links($this->links['href'], $this->links['title'], $this->links['text']);
            $this->rebuild_titles();
            $this->rebuild_embeds();
        }

}

class BuzzFeed extends Ripper {
    public $pre;

        public function load($url) {
            $this->doc = file_get_html($url, $use_include_path = false, $context=null, $offset = -1, $maxLen=-1, $lowercase = true, $forceTagsClosed=true, $target_charset = DEFAULT_TARGET_CHARSET, $stripRN=true, $defaultBRText=false);
            $strippables = ['script', 'style', 'a[class^=share]'];

            /*foreach ($this->doc->find('script') as $s) {
                if (preg_match('/big_image_root\s*:\s*\'(S+)\',/', $s->innertext, $matches)) {
                    $this->pre = $matches[1];
                    echo "Matches: " . print_r($matches, true) . "<br />";
                } else echo "no matches";
            }*/

            $this->doc = $this->doc->find('div[id=buzz_sub_buzz]')[0];    //narrow things down a bit
        }

        public function rebuild_links($href, $title, $text) {
            if (count($href) > 0) {
                for ($i = 0; $i < count($href); $i++) {
                    if (stripos($href[$i], 'buzzfeed.com/') || stripos($href[$i], 'buzzfed.com/')) {
                        $this->doc->find('a',$i)->outertext = $this->doc->find('a',$i)->plaintext;
                    } else if ($href[$i] == "#" || $href[$i] == '' || $href[$i] == null) {
                        $this->doc->find('a',$i)->outertext = '';
                    } else if ($title[$i] !== null) {
                        $this->doc->find('a',$i)->outertext = '<a href="' . $href[$i] . '" title="' .  $title[$i] . '">' . $text[$i] . '</a>';
                    } else {
                        $this->doc->find('a',$i)->outertext = '<a href="' . $href[$i] . '">' . $text[$i] . '</a>';
                    }
                }
            }
        }

        public function rebuild_titles() {
            foreach ($this->doc->find('h1, h2, h3, h4, h5') as $h)
                $h->outertext = '<br /><br /><strong>' . $h->innertext . '</strong>';
        }

        public function Save_Attributes() {
            $this->save_attrs('img', src, $this->imgs['src']);
            $this->save_attrs('img', 'rel:bf_image_src', $this->imgs['bf_src']);
            $this->save_attrs('img', alt, $this->imgs['alt']);
            $this->save_attrs('a', href, $this->links['href']);
            $this->save_attrs('a', title, $this->links['title']);
            $this->save_attrs('a', innertext, $this->links['text']);
        }

        public function Trim() {
            $this->doc = strip_tags($this->doc, '<img><a><strong><br>');
            $this->doc = preg_replace('/\s+/', ' ', $this->doc);
        }

        public function Rip_Images($post_id) {
            for ($i = 0; $i < count($this->imgs['src']); $i++) {
                if (stripos($this->imgs['src'][$i], 'buzzfed'))
                    $this->pre = parse_url($this->imgs['src'][$i])[scheme] . "://" . parse_url($this->imgs['src'][$i])[host];

                if (!empty($this->imgs['bf_src'][$i]) && !filter_var($this->imgs['bf_src'][$i], FILTER_VALIDATE_URL))
                    $this->imgs['src'][$i] = $this->pre . $this->imgs['bf_src'][$i];
                else if (!empty($this->imgs['bf_src'][$i]) && filter_var($this->imgs['bf_src'][$i], FILTER_VALIDATE_URL))
                    $this->imgs['src'][$i] = $this->imgs['bf_src'][$i];
            }
            //echo "Regular SRC: " . print_r($this->imgs['src'], TRUE) . "<br />";
            //echo "Rel_BF SRC: " . print_r($this->imgs['bf_src'], TRUE) . "<br />";
            $this->rebuild_imgs('img', $this->imgs['src'], $this->imgs['alt'], $post_id);
        }

        public function Rebuild() {
            $this->rebuild_links($this->links['href'], $this->links['title'], $this->links['text']);
            $this->rebuild_titles();
            $this->rebuild_embeds();
        }

}
?>
