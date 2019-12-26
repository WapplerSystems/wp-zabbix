# wp-zabbix
Zabbix client for WordPress.

## What does it do?



## Installation

  1. Install the wp-zabbix plugin.
  2. Define a key in your _wp-config.php_ file:
     
     `define('WPZABBIX_KEY', 'eoij4r93440frexampler0rugt0rug0tru');`

     Please use a long key for security.

  3. Import the zabbix template from wp-zabbix/template/wordpress.xml
  4. Create a host in zabbix
  5. Add a macro to this host called:
     `{$WORDPRESS_CLIENT_KEY}` with the value of your generated key