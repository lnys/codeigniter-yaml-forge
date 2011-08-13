CodeIgniter YAML Forge
======================
This library aims to generate database schemas from a basic YAML defined schema. I find YAML notation a lot easier to read/and write than verbose SQL scripts or $this->dbforge->add_field( ... ) and I'm hoping this abstraction will save me some time and some keystrokes. This spark depends on Dan Horrigan's CodeIgniter wrapper class for the Spyc Yaml library to parse the YAML and utilizes CodeIgniter's dbforge library to perform the database manipulation.

I've designed it with my conventions in mind (I use WanWizard's DataMapper ORM) which means this might not be for everyone but please do take a look. More features and more customizable options will be included as and when the need arises.

Currently the library can do the following:

 *   Create tables
 *   Create fields
 *   Add dummy data
 *   Add keys
 *   Modify columns (existing tables)
 *   Add columns (existing tables)
 *   Drop colums (existing tables)
 *   Automagically add relationship fields and tables (based on the DataMapper ORM conventions)

Documentation
-------------

You can use Sparks (getsparks.org) or just download the library into your CI application/libraries folder.

Install (this) YAML Forge spark:

    php tools/spark install -v0.1.1 yaml-forge

This example controller shows the basic usage:

    class Test_yaml_forge extends CI_Controller {
    
        public function index()
        {
            $this->load->spark('yaml-forge/0.1.1');
        
            $this->yaml_forge->set_debug(TRUE);       // debug: verbose output, FALSE by default
            $this->yaml_forge->set_auto_id(TRUE);     // auto_id: adds an 'id' field to each table, TRUE by default
            $this->yaml_forge->set_drop_tables(TRUE); // drop_tables: drops a table before creating, FALSE by default
			$this->yaml_forge->set_join_table_prefix('join_'); // join_table_prefix: adds a table prefix to join tables, empty by default

            $this->yaml_forge->generate( APPPATH . '../sparks/yaml-forge/0.1.1/test/test_schema.yaml' );
        }
    }

Write some YAML that describes your schema (look at ./tests/text_schema.yaml
for a more detailed explanation of all features):

    "users":
      has_one: [user_group]
      fields: 
        first_name: varchar|255
        last_name: varchar|255

    "categories":
      has_many: [posts]
      fields: 
        name: varchar|255

    "posts":
      fields:
        has_many: [categories]
        has_one: [users]
        title: varchar|255
        body: text
        date_created: datetime
        date_updated: "TIMESTAMP ON UPDATE CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
      data:
        1:
          title: "My first post"
          body: "Check out this body text dudes!"
          date_created: "2011-01-01 00:00:00"

Changelog
---------
0.1.0 -> 0.1.1

 * Data operation will now serialize nested arrays
 * Added a join_table_prefix setting
 * Added a drop_table operation
 * Added a truncate_table operation
 * Fixed a critical bug in the has_many table creation (I hope no-one was using it because it didn't work)
 * Now with phpdoc comments
 * Added the typical disclaimer

0.0.1 -> 0.1.0

 * Almost everything is different, forget the first version

0.0.1
 
 * And then there was YAML Forge

Contributing
------------
Drop me a line if you use this library or have any thoughts about improvements. Contribution would also be great if you want to get down and dirty with my codes.

Disclaimer
----------
Need it be said? This library is designed to perform operations on *databases*. You could lose data. You could wipe-out your work. Make backups and be careful. Enjoy.
