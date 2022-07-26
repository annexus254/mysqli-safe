<?php

    define('DEDUCE_TYPE' , 1);
    define('REUSE_STMT'  , 2);

    class mysqli_safe
    {
        private ?mysqli      $db                =   null;

        private ?mysqli_stmt $stmt              =   null;
        private ?string      $stmt_template     =   null;
        private ?array       $stmt_params       =   null;
        private ?string      $stmt_types        =   null;
        private ?string      $stmt_params_sep   =   null;

        private ?array       $db_info           =   null;

        //Options
        private bool         $deduce_type       =   true;
        private bool         $reuse_stmt        =   false;
        //

        public  ?string      $connect_error     =   null;

        public  ?string      $stmt_bind_error   =   null;
        public  ?string      $stmt_error        =   null;
        public  ?int         $stmt_errno        =   0;

        public  ?string      $db_error          =   null;
        public  ?int         $db_errno          =   0;


        /**
         * This object represents a safe connection between PHP and a MYSQL/MYSQL-like database.
         * @param   string  hostname    Hostname/Ip-address of the server application
         * @param   string  username    The MYSQL username
         * @param   string  password    Password for the given MYSQL user
         * @param   string  database    Specifies the default database to perform queries on.
         * @param   bool    connect     Determines whether the database connection is opened during the object construction. If not, the user will have to call the connect method before using the resultany Mysqli-safe object.
         * @return mixed
         */
        public function __construct(string $hostname , string $username , string $password , string $database , string $stmt_params_sep = ',' , bool $connect = true)
        {
            $this->db_info                      =   array($hostname , $username , $password , $database);
            $this->stmt_params_sep              =   $stmt_params_sep;

            if($connect)
                $this->connect();
        }

        /**
         * Attempts a connection to a database whose information is new or existing
         * @param   mixed   newdb_info  [Optional]Information for the new database(hostname,username,password,database). See the constructor's arguments for details
         * @return  bool    true on success, false on failure
         */
        public function connect(...$newdb_info) : bool
        {
            //Clear any previous connection error
            if($this->connect_error)
            {
                $this->connect_error            =   null;
                $this->db_errno                 =   0;
            }
            //Reset any previous database connection
            if($this->resetDB() == false)
            {
                $this->connect_error            =   "could not close the previous database connection";
                return false;
            }

            if(!empty($newdb_info))
                $this->db                       =   new mysqli(...$newdb_info);
            else
                $this->db                       =   new mysqli(...$this->db_info);

            if($this->db->connect_error)
            {
                $this->connect_error            =   $this->db->connect_error;
                $this->db_errno                 =   $this->db->errno;
                //db is not a fully initialized object, so calling close() on it will result in an error
                $this->db                       =   null;
                return false;
            }

            //Once we have reached this point, a database connection has been successfully established

            if($this->reuse_stmt)
            {
                if($this->stmt_template && $this->stmt_types && $this->stmt_params)
                {
                    if($this->set($this->stmt_template , $this->stmt_types , ...$this->stmt_params) == false)
                    {
                        $this->connect_error    =   "statement preparation failed";
                        $this->resetDB();
                        return false;
                    }
                }
                else
                {
                    $this->connect_error        =   "no previous statement has been found";
                    $this->resetDB();
                    return false;
                }
                    
            }
            return true;
        }

        /**
         * Creates/Sets a query template and binds parameters to be used when executing a query
         * @param string $query_template    The template to be used for stmt preparation
         * @param mixed  $query_params      The parameters to be passed to the prepared stmt upon execution of the query. Note that by default, the type information is inferred by the class itself.
         * @return bool - true on success, false on failure
         */
        public function set(string $query_template , &...$query_params) : bool
        {
            //Reset any existing stmt first
            if($this->resetStmt() == false)
                return false;
            
            $this->stmt                         =   $this->db->stmt_init();
            $result                             =   $this->stmt->prepare($query_template);
            
            if(!$result)    
            {
                $this->stmt_error               =   $this->stmt->error;
                $this->stmt_errno               =   $this->stmt->errno;
                //stmt is not a fully initialized object, so calling close() on it will result in an error
                $this->stmt                     =   null;
                return false;
            }

            

            //Once we've reached this point, we have a fully prepared statement

            $this->stmt_template                =   $query_template;

            if($this->deduce_type)
            {   
                $this->stmt_types               =   "";
                foreach ($query_params as $key => $value) {
                    $val_type                   =   gettype($value)[0];
                    if( in_array($val_type , array('i','d','s')) )
                        $this->stmt_types       .=  $val_type;
                    else
                        $this->stmt_types       .=  'b';
                }
            }

            foreach ($query_params as $key => $value) 
                $query_params[$key]             =   htmlspecialchars($value , ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE | ENT_QUOTES);
            $this->stmt_params                  =   $query_params;

            try
            {
                $this->stmt->bind_param($this->stmt_types , ...$this->stmt_params);
            }
            catch(ArgumentCountError $error)
            {
                //There might be something wrong with the template, types or params, so don't store them
                $this->stmt_template            =   null;
                $this->stmt_types               =   null;
                $this->stmt_params              =   null;

                $this->resetStmt();

                $this->stmt_error               =   "binding parameters failed";
                $this->stmt_errno               =   $this->stmt->errno;
                $this->stmt_bind_error          =   sprintf("%s",$error);

                return false;
            }
            return true;
        }

        /**
         * Performs the actual query
         * @param   mixed $query_params [Optional]The parameters to be passed to the prepared stmt. Note that this is not a must and is also not recommended. Use the set() method instead if you want to change the query parameters.
         * @return  mixed mysqli_result if query operation is retrieval otherwise true on success, false on failure
         */
        public function query(...$query_params) //: mysqli_result|bool (php >= 8.0.0 )
        {
            if($this->stmt->execute(...$query_params))
            {
                $results                        =   $this->stmt->get_result();
                return $results ? $results : true;
            }
            else
            {
                $this->db_error                 =   $this->stmt->error;
                $this->db_errno                 =   $this->stmt->errno;
                return false;
            }
        }

        /**
         * Sets the various options that control the operation of a mysqli_safe object
         * @param   int     $option     Option to be set or removed
         * @param   mixed   $value      Value of the given option
         * @param   mixed   $params     Parameters,if any, of the given option, e.g. in type deduction
         * @return  bool    true on success, false on failure
         */
        public function setopt(int $option , /* mixed  ( php >= 8.0.0 ) */ $value , ...$params)    : bool
        {
            if($option == DEDUCE_TYPE)
            {
                $this->deduce_type              =   (bool)$value;
                $this->stmt_types               =   $params[0];
                return true;
            }
            elseif($option == REUSE_STMT)
            {
                $this->reuse_stmt               =   (bool)$value;
                return  true;
            }
        }

        /**
         * Closes any existing statement and resets the state of all statement error attributes
         * @param   void    void
         * @return  bool    true on success, false on failure
         */
        public function resetStmt() : bool
        {
            //Reset any previous stmt errors first
            if($this->stmt_error)
            {
                $this->stmt_error               =   null;
                $this->stmt_errno               =   0;
            }
                

            if($this->stmt)
            {
                if($this->stmt->close())
                {
                    $this->stmt                 =   null;
                    return true;
                }
                else
                {
                    $this->stmt_error           =   $this->stmt->error;
                    $this->stmt_errno           =   $this->stmt->errno;
                    return false;
                }
            }
            else
            {
                //The stmt is already reset, so there's nothing to do :)
                return true;
            }
        }

        /**
         * Closes any existing database connection and resets the state of all database error attributes
         * @param   void    void
         * @return  bool    true on success, false on failure
         */
        public function resetDB() : bool
        {
            if($this->db_error)
            {
                $this->db_error                 =   null;
                $this->db_errno                 =   0;
            }

            if($this->db)
            {
                if($this->db->close())
                {
                    if( $this->resetStmt() == false )
                    {
                        $this->db_error         =   "error closing the associated statement";
                        $this->db_errno         =   $this->stmt_errno;
                        return false;
                    }
                    $this->db                   =   null;
                    return true;
                }
                else
                {
                    $this->db_error             =   $this->db->error;
                    $this->db_errno             =   $this->db->errno;
                    return false;
                }
            }
            else
            {
                //The db is already reset, so there's nothing to do :)
                return true;
            }
        }

        /**
         * Returns the database connection attribute
         * @param   void    void
         * @return  mysqli  a mysqli object or null if no db connection exists yet
         */
        public function getDB() : ?mysqli
        {
            return $this->db;
        }

        /**
         * Returns the prepared statment attribute
         * @param   void    void
         * @return  mysqli_stmt  a mysqli_stmt object or null if no prepared statement has been created.
         */
        public function getStmt() : ?mysqli_stmt
        {
            return $this->stmt;
        }

        /**
         * Returns the Statement Parameters attribute as a string
         * @param   void    void
         * @return  string  a string containing the statement parameters or null if no statement has been created.
         */
        public function getStmtParams() : ?string
        {
            if($this->stmt_params !== null)
            {
                $stmt_params_string            =   implode($this->stmt_params_sep,$this->stmt_params);
                return $stmt_params_string;
            }
            else
            {
                return null;
            }
        }

        public function __destruct()
        {
            $this->resetStmt();
            $this->resetDB();
        }
    }
