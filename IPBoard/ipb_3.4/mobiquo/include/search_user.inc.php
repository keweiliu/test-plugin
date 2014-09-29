<?php
/*======================================================================*\
|| #################################################################### ||
|| # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # This file is part of the Tapatalk package and should not be used # ||
|| # and distributed for any other purpose that is not approved by    # ||
|| # Quoord Systems Ltd.                                              # ||
|| # http://www.tapatalk.com | https://tapatalk.com/license.php       # ||
|| #################################################################### ||
\*======================================================================*/
defined('IN_MOBIQUO') or exit;

class tapatalk_search_user extends ipsAjaxCommand
{
    public $total = 0;
    public $results = array();
    
    public function doExecute( ipsRegistry $registry )
    {
        $name = IPSText::convertUnicode( $this->convertAndMakeSafe( $this->request['name'], 0 ), true );
        $name = IPSText::convertCharsets( $name, 'utf-8', IPS_DOC_CHAR_SET );
        $start = intval($this->request['st']);
        $limit = intval($this->request['perpage']);
        
        if ( ! $this->memberData['member_id'] )
        {
            get_error('Please login');
        }
        
        if ( IPSText::mbstrlen( $name ) >= 3 )
        {
            $this->DB->build( array( 'select'   => '*',
                                     'from'     => array( 'members' => 'm' ),
                                     'where'    => "m.members_l_display_name LIKE '" . $this->DB->addSlashes( strtolower( $name ) ) . "%'",
                            ) );
            
            $this->DB->execute();
            $this->total = $this->DB->getTotalRows();
            
            if ($this->total)
            {
                $this->DB->build( array( 'select'   => 'm.member_id, m.members_display_name',
                                         'from'     => array( 'members' => 'm' ),
                                         'where'    => "m.members_l_display_name LIKE '" . $this->DB->addSlashes( strtolower( $name ) ) . "%'",
                                         'order'    => $this->DB->buildLength( 'm.members_display_name' ) . ' ASC',
                                         'limit'    => array( $start, $limit )
                                ) );
                
                $this->DB->execute();
                if ( $this->DB->getTotalRows() )
                {
                    while( $r = $this->DB->fetch() )
                    {
                        $this->results[] = $r;
                    }
                }
            }
        }
    }
}