<?php
/**
 * Recently items to display on home page
 */
class Loco_data_RecentItems extends Loco_data_Option {

    /**
     * Global instance of recent items
     * @var Loco_data_RecentItems
     */
    private static $current;

    
    /**
     * {@inheritdoc}
     */
    public function getKey(){
        return 'recent';
    }


    /**
     * @return Loco_data_RecentItems
     */
    public static function get(){
        if( ! self::$current ){
            self::$current = new Loco_data_RecentItems;
            self::$current->fetch();
        }
        return self::$current;
    }



    /**
     * Trash data and remove from memory
     */
    public static function destroy(){
        $tmp = new Loco_data_RecentItems;
        $tmp->remove();
        self::$current = null;
    }



    /**
     * @internal
     * @return Loco_data_RecentItems 
     */
    private function push( $object, array $indexes ){
        foreach( $indexes as $key => $id ){
            $stack = isset($this[$key]) ? $this[$key] : array();
            // remove before add ensures latest item appended to hashmap
            unset($stack[$id]);
            $stack[$id] = time();
            $this[$key] = $stack;
            // TODO prune stack to maximum length
        }
        return $this;
    }



    /**
     * @return array
     */
    private function getItems( $key, $offset, $count ){
        $stack = isset($this[$key]) ? $this[$key] : array();
        // hash map should automatically be in "push" order, meaning most recent last 
        // sorting gives wrong order for same-second updates (only relevent in tests, but still..)
        // asort( $stack, SORT_NUMERIC );
        $stack = array_reverse( array_keys( $stack ) );
        if( is_null($count) && 0 === $offset ){
            return $stack;
        }
        return array_slice( $stack, $offset, $count, false );
    }


    /**
     * @return int
     */
    private function hasItem( $key, $id ){
        if( isset($this[$key]) && ( $items = $this[$key] ) && isset($items[$id]) ){
            return $items[$id];
        }
        return 0;
    }


    /**
     * Push bundle to the front of recent bundles stack
     * @return Loco_data_RecentItems 
     */
    public function pushBundle( Loco_package_Bundle $bundle ){
        return $this->push( $bundle, array( 'bundle' => $bundle->getId() ) );
    }


    /**
     * Get bundle IDs
     * @return array
     */
    public function getBundles( $offset = 0, $count = null ){
        return $this->getItems('bundle', $offset, $count );
    }


    /**
     * Check if a bundle has been recently used
     * @return int timestamp item was added, 0 if absent
     */
    public function hasBundle( $id ){
        return $this->hasItem( 'bundle', $id );
    }


    /**
     * TODO other types of item
     * Push project to the front of recent bundles stack
     * @return Loco_data_RecentItems
     *
    public function pushProject( Loco_package_Project $project ){
        return $this;
    }*/
    

}
