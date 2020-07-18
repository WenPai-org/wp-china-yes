<?php
/**
 * Abstraction of WordPress roles and capabilities and how they apply to Loco.
 * 
 * - Currently only one capability exists, proving full access "loco_admin"
 * - Any user with super admin privileges automatically inherits this permission
 * - A single custom role is added called "translator"
 */
class Loco_data_Permissions {
    
    /**
     * Loco capabilities applicable to roles
     * @var array
     */
    private static $caps = array('loco_admin');
    
    
    /**
     * Polyfill for wp_roles which requires WP >= 4.3
     * @return WP_Roles
     */
    private static function wp_roles(){
        global $wp_roles;
        if( ! isset($wp_roles) ){
            get_role('ping');
        }
        return $wp_roles;
    }


    /**
     * Set up default roles and capabilities
     * @return WP_Roles
     */
    public static function init(){
        $roles = self::wp_roles();
        $apply = array();
        // ensure translator role exists and is not locked out
        $role = $roles->get_role('translator');
        if( $role instanceof WP_Role ){
            $role->has_cap('read') || $role->add_cap('read');
        }
        // else absence of translator role indicates first run
        // by default we'll initially allow full access to anyone that can manage_options
        else {
            $apply['translator'] = $roles->add_role( 'translator', 'Translator', array('read'=>true) );
            /* @var $role WP_Role */
            foreach( $roles->role_objects as $id => $role ){
                if( $role->has_cap('manage_options') ){
                    $apply[$id] = $role;
                }
            }
        }
        // fix broken permissions whereby super admin cannot access Loco at all.
        // this could happen if another plugin added the translator role before hand.
        if( ! isset($apply['administrator']) && ! is_multisite() ){
            $apply['administrator'] = $roles->get_role('administrator');
        }
        /* @var $role WP_Role */
        foreach( $apply as $role ){
            if( $role instanceof WP_Role ){
                foreach( self::$caps as $cap ){
                    $role->has_cap($cap) || $role->add_cap($cap);
                }
            }
        }
        return $roles;
    }


    /**
     * Construct instance, ensuring default roles and capabilities exist
     */
    public function __construct(){
        self::init();
    }


    /**
     * @return WP_Role[]
     */
    public function getRoles(){
        $roles = self::wp_roles();
        return $roles->role_objects;
    }


    /**
     * Check if role is protected such that user cannot lock themselves out when modifying settings
     * @param WP_Role WordPress role object to check
     * @return bool
     */
    public function isProtectedRole( WP_Role $role ){
        // if current user has this role and is not the super user, prevent lock-out
        $user = wp_get_current_user();
        if( $user instanceof WP_User && ! is_super_admin($user->ID) && $user->has_cap('manage_options') ){
            return in_array( $role->name, $user->roles, true );
        }
        // admin users of single site install must never be denied access
        // note that there is no such thing as a network admin role, but network admins have all permissions
        return is_multisite() ? false : $role->has_cap('delete_users');
    }


    /**
     * Completely remove all Loco permissions, as if uninstalling
     * @return Loco_data_Permissions
     */
    public function remove(){
        /* @var $role WP_Role */
        foreach( $this->getRoles() as $role ){
            foreach( self::$caps as $cap ){
                $role->has_cap($cap) && $role->remove_cap($cap);
            }
        }
        // we'll only remove our custom role if it has no capabilities other than admin access
        // this avoids breaking other plugins that use it, or added it before Loco was installed.
        if( $role = get_role('translator') ){
            if( ! $role->capabilities || array('read') === array_keys($role->capabilities) ){
                remove_role('translator');
            }
        }
        return $this;
    }


    /**
     * Reset to default: roles include no Loco capabilities unless they have super admin privileges
     * @return WP_Role[]
     */
    public function reset(){
        $roles = $this->getRoles();
        /* @var $role WP_Role */
        foreach( $roles as $role ){
            // always provide access to site admins on first run
            $grant = $this->isProtectedRole($role);
            foreach( self::$caps as $cap ){
                if( $grant ){
                    $role->has_cap($cap) || $role->add_cap($cap);
                }
                else {
                    $role->has_cap($cap) && $role->remove_cap($cap);
                }
            }
        }
        return $roles;
    }


    /**
     * Get translated WordPress role name
     * @param string
     * @return string
     */
    public function getRoleName( $id ){
        if( 'translator' === $id ){
            $label = _x( 'Translator', 'User role', 'loco-translate' );
        }
        else {
            $names = self::wp_roles()->role_names;
            $label = isset($names[$id]) ? translate_user_role( $names[$id] ) : $id;
        }
        return $label;
    }


    /**
     * Populate permission settings from posted checkboxes
     * @param string[]
     * @return self
     */
    public function populate( array $caps ){
        // drop all permissions before adding (cos checkboxes)
        $roles = $this->reset();
        foreach( $caps as $id => $checked ){
            if( isset($roles[$id]) ){
                $role = $roles[$id];
                /* @var $role WP_Role */
                foreach( self::$caps as $cap ){
                    if( ! empty($checked[$cap]) ){
                        $role->has_cap($cap) || $role->add_cap($cap);
                    }
                }
            }
        }
        return $this;
    }

}
