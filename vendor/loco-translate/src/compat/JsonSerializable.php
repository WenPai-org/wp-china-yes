<?php
// @codeCoverageIgnoreStart

/**
 * Placeholder for missing interface in PHP < 5.4.
 * Can't be invoked automatically, so always do: json_encode( $obj->jsonSerialize() )
 * Note that this shim is also present in WordPress >= 4.4.0
 */
if( ! interface_exists('JsonSerializable') ){
    interface JsonSerializable {
        public function jsonSerialize();
    }
}

// @codeCoverageIgnoreEnd

/**
 * Redundant interface so this file will autoload when JsonSerializable is referenced
 * @internal
 */
interface Loco_compat_JsonSerializable extends JsonSerializable {
    
}
