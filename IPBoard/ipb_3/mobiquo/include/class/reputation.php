<?php
/*======================================================================*\
|| #################################################################### ||
|| # Copyright &copy;2009 Quoord Systems Ltd. All Rights Reserved.    # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # This file is part of the Tapatalk package and should not be used # ||
|| # and distributed for any other purpose that is not approved by    # ||
|| # Quoord Systems Ltd.                                              # ||
|| # http://www.tapatalk.com | http://www.tapatalk.com/license.html   # ||
|| #################################################################### ||
\*======================================================================*/
defined('IN_MOBIQUO') or exit;

class mobi_reputation extends ipsAjaxCommand
{
    protected $result;

    public function doExecute( ipsRegistry $registry )
    {
        switch( $this->request['do'] )
        {
            case 'add_rating':
                $this->_doRating();
            break;

            case 'more':
                $this->_more();
            break;

            case 'view':
                $this->_viewRep();
            break;
        }

        return true;
    }

    protected function _viewRep()
    {
        $this->lang->loadLanguageFile( array( 'public_topic' ), 'forums' );

        if ( !$this->memberData['gbw_view_reps'] )
        {
            $this->returnJsonError('no_permission');
        }

        $repApp        = $this->request['repApp'];
        $repType    = $this->request['repType'];
        $repId        = intval($this->request['repId']);

        /* Get data */
        $reps        = array();
        $members    = array();

        $this->DB->build( array(
                                'select'    => 'member_id, rep_rating',
                                'from'        => 'reputation_index',
                                'where'        => "app='{$repApp}' AND type='{$repType}' AND type_id='{$repId}'",
                                'order'        => 'rep_date',
                        )        );
        $q = $this->DB->execute();

        while ( $r = $this->DB->fetch( $q ) )
        {
            $reps[ $r['member_id'] ]    = $r;
            $members[ $r['member_id'] ]    = $r['member_id'];
        }

        if( count($members) AND count($reps) )
        {
            $_members    = IPSMember::load( $members );

            foreach( $reps as $memId => $repData )
            {
                $reps[ $memId ]['member']    = $_members[ $memId ];
            }
        }

        return $this->returnHtml( $this->registry->output->getTemplate('global_other')->reputationPopup( $reps ) );
    }

    protected function _more()
    {
        $app   = trim( $this->request['f_app'] );
        $type  = trim( $this->request['f_type'] );
        $id    = intval( $this->request['f_id'] );

        /* Get the rep library */
        $classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/class_reputation_cache.php', 'classReputationCache' );
        $repCache = new $classToLoad();

        /* Fetch members who have wanted to favorite this item */
        $data = $repCache->getDataByRelationshipId( array( 'app' => $app, 'type' => $type, 'id' => $id, 'rating' => 1 ) );

        return $this->returnHtml( $this->registry->output->getTemplate( 'global_other' )->repMoreDialogue( $data, $id ) );
    }

    protected function _doRating()
    {
        $app     = $this->request['app_rate'];
        $type    = $this->request['type'];
        $type_id = intval( $this->request['type_id'] );
        $rating  = intval( $this->request['rating'] );

        if( ! $app || ! $type || ! $type_id || ! $rating )
        {
            $this->result = false;
        }

        $classToLoad = IPSLib::loadLibrary( IPS_ROOT_PATH . 'sources/classes/class_reputation_cache.php', 'classReputationCache' );
        $repCache = new $classToLoad();

        return $repCache->addRate( $type, $type_id, $rating, '', 0, $app );
    }
}