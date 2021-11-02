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


        public function __construct(string $server , string $username , string $pwd , string $database , bool $connect = true)
        {
            $this->db_info                      =   array($server , $username , $pwd , $database);

            if($connect)
                $this->connect();
        }

        /**
         * Attempts a connection to a database whose information is new or existing
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
         * Creates/Sets a query template and parameters to be used when executing a query
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
                        $this->stmt_type        .=  'b';
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

        public function getDB() : ?mysqli
        {
            return $this->db;
        }

        public function getStmt() : ?mysqli_stmt
        {
            return $this->stmt;
        }

        public function __destruct()
        {
            $this->resetStmt();
            $this->resetDB();
        }
    }
?>