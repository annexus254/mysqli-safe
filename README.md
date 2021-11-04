<h1 align="center">                                  Mysqli-Safe </h1>

<h2 align="center"><i> A simple, easy to use and secure way of accessing a Mysql database from within your PHP programs </i></h2>

[![Build Status](https://travis-ci.org/joemccann/dillinger.svg?branch=master)](https://travis-ci.org/joemccann/dillinger)

Mysqli-safe is a wrapper around the mysqli extension in PHP that bundles together the extension itself and Mysql prepared statements into one neat and succint API that's very easy to use.

In fact, you can perform an initial database query in just three easy steps:
1. Open a database connection by creating a mysqli_safe object.
2. Set the query and its parameters by calling the set method of the created object.
3. Perform the query!

**Example**
> $db   =   new mysqli_safe('localhost' , 'username' , 'password' , 'database');
> 
> $res  =   $db->set("SELECT * FROM table WHERE id = ? AND name = ?" , $id , $name);
> 
> $result = $db->query();

## Features
In addition to providing a short and concise API, the mysqli_safe class also offers the following features:
### 1.Object Re-use
With mysqli_safe, you do not really need to create a new object in order to connect to a new database.Instead, just call the connect method and pass in the details of the new database, and the class will handle the rest for you. ( Including closing any previously opened db connection)

**Example**
> $res = $db->connect('localhost' , 'new_username' , 'password' , 'new_database');

### 2.Compatibility
Mysqli_safe is also backwards compatible with any existing programs that use the mysqli extension. To migrate to using this wrapper class, just add a set method and pass in the existing query and its parameters and then call the query method. The rest of the program should continue working without altering anything else.

**Example**
> *before*
> 
> ...
> 
> $result = $db->query("SELECT * FROM table WHERE id = '$id' AND name = '$name');
> 
> ...


> *after*
> 
> ...
> 
> $setresult = $db->set("SELECT * FROM table WHERE id = ? AND name = ? , $id , $name);
> 
> $result = $db->query();
> 
> ...
> 
### 3.Type Deduction
With mysqli_safe you do not have to worry about passing the correct type string like in the traditional way of creating prepared statements. The class will deduce the types for you and create the correct string when you call the set method. However, if you do not want this behaviour, you can always turn this feature off by calling the setopt method and passing your own type string.

**Example**
> $res  =   $db->setopt(DEDUCE_TYPE , false , "is");
> 
> $setresult = $db->set("SELECT * FROM table WHERE id = ? AND name = ? , $id , $name);
> 
> $result = $db->query();

### 4.Query Statement Re-use
Mysqli_safe also allows you to re-use the same query statement and parameters across mutiple databases, if they have the same structure. All you have to to is to enable the REUSE_STMT option and the class will do the rest for you.

**Example**
> $setresult = $db->set("SELECT * FROM table WHERE id = ? AND name = ? , $id , $name);
> 
> $result = $db->query();
> 
> $res  =   $db->setopt(REUSE_STMT , true);
> 
> $res = $db->connect('localhost' , 'new_username' , 'password' , 'new_database');
> 
> $res = $db->query();

### 5.Built-in Protection Against Some Common Web Attacks
Mysqli_safe uses Mysql's prepared statements and PHP's built-in htmlspecialchars function to guarantee protection against SQL injection and XSS attacks respectively, when reading from or writing to your database. This ensures that each and every one of your database accesses is secure with the programmer having to think much about it, allowing him/her to focus on the core logic of his/her application.

