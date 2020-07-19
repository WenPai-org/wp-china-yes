<?php
/**
 * Downgraded for PHP 5.2 compatibility. Do not edit.
 */
function loco_parse_wp_locale( $tag ){ if( ! preg_match( '/^([a-z]{2,3})(?:[-_]([a-z]{2}))?(?:[-_]([a-z0-9]{3,8}))?$/i', $tag, $tags ) ){ throw new InvalidArgumentException('Invalid WordPress locale: '.json_encode($tag) ); } $data = array( 'lang' => strtolower( $tags[1] ), ); if( isset($tags[2]) && ( $subtag = $tags[2] ) ){ $data['region'] = strtoupper($tags[2]); } if( isset($tags[3]) && ( $subtag = $tags[3] ) ){ $data['variant'] = strtolower($tags[3]); } return $data; }
