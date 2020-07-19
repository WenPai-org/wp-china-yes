<?php
/**
 * Data object persisted as a WordPress user meta entry under the loco_prefs key
 * 
 * @property string $credit Last-Translator credit, defaults to current display name
 */
class Loco_data_Preferences extends Loco_data_Serializable {

    /**
     * User preference singletons
     * @var array
     */
    private static $current = array();

    /**
     * ID of the currently operational user
     * @var int
     */    
    private $user_id = 0;

    /**
     * Available options and their defaults
     * @var array
     */
    private static $defaults = array (
        'credit' => '',
    );


    /**
     * Get current user's preferences
     * @return Loco_data_Preferences
     */
    public static function get(){
        $id = get_current_user_id();
        if( ! $id ){
            throw new Exception('No current user');
        }
        if( isset(self::$current[$id]) ){
            return self::$current[$id];
        }
        $prefs = self::create($id);
        self::$current[$id] = $prefs;
        $prefs->fetch();
        return $prefs;
    }


    /**
     * Create default settings instance
     * @return Loco_data_Preferences
     */
    public static function create( $id ){
        $prefs = new Loco_data_Preferences( self::$defaults );
        $prefs->user_id = $id;
        return $prefs;
    }


    /**
     * Persist object in WordPress usermeta table
     * @return bool
     */
    public function persist(){
        return update_user_meta( $this->user_id, 'loco_prefs', $this->getSerializable() ) ? true : false;
    }


    /**
     * Retrieve and unserialize this object from WordPress usermeta table
     * @return bool whether object existed in cache
     */
    public function fetch(){
        $data = get_user_meta( $this->user_id, 'loco_prefs', true );
        try {
            $this->setUnserialized($data);
        }
        catch( InvalidArgumentException $e ){
            return false;
        }
        return true;
    }


    /**
     * Delete usermeta entry from WordPress
     * return bool
     */
    public function remove(){
        $id = $this->user_id;
        self::$current[$id] = null;
        return delete_user_meta( $id, 'loco_prefs' );
    }


    /**
     * Populate all settings from raw postdata. 
     * @param array
     * @return Loco_data_Preferences
     */
    public function populate( array $data ){
        // set all keys present in array
        foreach( $data as $prop => $value ){
            try {
                $this->offsetSet( $prop, $value );
            }
            catch( InvalidArgumentException $e ){
                // skipping invalid key
            }
        }
        return $this;
    }
    
    
    /**
     * Get default Last-Translator credit
     * @return string
     */
    public function default_credit(){
        $user = wp_get_current_user();
        $name = (string) $user->get('display_name');
        if( $user->get('user_login') === $name ){
            $name = '';
        }
        return $name;
    }

}