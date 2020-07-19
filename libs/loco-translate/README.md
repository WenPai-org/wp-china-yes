# Loco WordPress Plugin

### Translate plugins and themes directly in your browser

Git mirror of the official Loco WordPress plugin:  
[http://wordpress.org/plugins/loco-translate/](http://wordpress.org/plugins/loco-translate/)

**Please don't submit pull requests** this is just a mirror of the SVN repository at:  
[https://plugins.svn.wordpress.org/loco-translate/trunk/](https://plugins.svn.wordpress.org/loco-translate/trunk/)

Please report issues in the WordPress plugin directory support forum:  
[http://wordpress.org/support/plugin/loco-translate](http://wordpress.org/support/plugin/loco-translate)


## Installation

Note that the actual name of the plugin is "loco-translate" not "wp-loco". It's renamed on Github to differentiate it as a WordPress plugin. 

Add the plugin to your WordPress project via Git as follows:

    $ git submodule add git@github.com:loco/wp-loco.git wp-content/plugins/loco-translate
    
If you want to use a stable release listed in the WordPress plugin directory, you can checkout by tag, e.g:

    $ cd wp-content/plugins/loco-translate 
    $ git fetch origin --tags
    $ git checkout tags/x.y.z
    
Be sure to check out the latest version, replacing `x.y.z` in above example with the release number shown below.

[![Latest Version](https://img.shields.io/github/release/loco/wp-loco.svg?style=flat-square)](https://github.com/loco/wp-loco/releases)

## Contributing

There is no issue tracker here. Please submit bugs or feature requests in the [WordPress support forum](http://wordpress.org/support/plugin/loco-translate).

The Github repository is for people who want the latest development version of the plugin and prefer Git to [SVN](http://plugins.svn.wordpress.org/loco-translate/trunk/). This is not a collaborative project and there are no resources available for examining pull requests.
