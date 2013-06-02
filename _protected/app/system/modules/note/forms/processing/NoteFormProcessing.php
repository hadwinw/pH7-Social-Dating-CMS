<?php
/**
 * @author         Pierre-Henry Soria <ph7software@gmail.com>
 * @copyright      (c) 2012-2013, Pierre-Henry Soria. All Rights Reserved.
 * @license        GNU General Public License; See PH7.LICENSE.txt and PH7.COPYRIGHT.txt in the root directory.
 * @package        PH7 / App / System / Module / Note / Form / Processing
 */
namespace PH7;
defined('PH7') or die('Restricted access');

use
PH7\Framework\Mvc\Model\Engine\Db,
PH7\Framework\Mvc\Model\DbConfig,
PH7\Framework\Mvc\Request\HttpRequest,
PH7\Framework\Url\HeaderUrl,
PH7\Framework\Mvc\Router\UriRoute;

class NoteFormProcessing extends Form
{

    private $sMsg;

    public function __construct()
    {
        parent::__construct();

        $oNote = new Note;
        $oNoteModel = new NoteModel;
        $sCurrentTime = $this->dateTime->get()->dateTime('Y-m-d H:i:s');
        $iProfileId = $this->session->get('member_id');
        $iTimeDelay = (int) DbConfig::getSetting('timeDelaySendNote');

        if(!$oNote->checkPostId($this->httpRequest->post('post_id'), $iProfileId))
        {
            \PFBC\Form::setError('form_note', t('The ID of the article is invalid or incorrect.'));
        }
        elseif(!$oNoteModel->checkWaitSend($this->session->get('member_id'), $iTimeDelay, $sCurrentTime))
        {
            \PFBC\Form::setError('form_note', Form::waitWriteMsg($iTimeDelay));
        }
        else
        {
            $iApproved = (DbConfig::getSetting('noteManualApproval') == 0) ? '1' : '0';

            $aData = [
                'profile_id' => $iProfileId,
                'post_id' => $this->httpRequest->post('post_id'),
                'lang_id' => $this->httpRequest->post('lang_id'),
                'title' => $this->httpRequest->post('title'),
                'content' => $this->httpRequest->post('content', HttpRequest::ONLY_XSS_CLEAN), // HTML contents, So we use the constant: \PH7\Framework\Mvc\Request\HttpRequest::ONLY_XSS_CLEAN
                'slogan' => $this->httpRequest->post('slogan'),
                'tags' => $this->httpRequest->post('tags'),
                'page_title' => $this->httpRequest->post('page_title'),
                'meta_description' => $this->httpRequest->post('meta_description'),
                'meta_keywords' => $this->httpRequest->post('meta_keywords'),
                'meta_robots' => $this->httpRequest->post('meta_robots'),
                'meta_author' => $this->httpRequest->post('meta_author'),
                'meta_copyright' => $this->httpRequest->post('meta_copyright'),
                'enable_comment' => $this->httpRequest->post('enable_comment'),
                'created_date' => $sCurrentTime,
                'approved' =>  $iApproved
            ];

            if(!$oNoteModel->addPost($aData))
            {
                $this->sMsg = t('An error occurred while adding the article.');
            }
            else
            {
                $iNoteId = Db::getInstance()->lastInsertId();

                // Thumbnail
                $oPost = $oNoteModel->readPost($iNoteId, $iProfileId);
                $oNote->setThumb($this->file, $oNoteModel, $oPost);

                if(count($this->httpRequest->post('category_id', HttpRequest::ONLY_XSS_CLEAN)) > 3)
                {
                    \PFBC\Form::setError('form_note', t('You can not select more than 3 categories.'));
                    return; // Stop execution of the method.
                }

                // WARNING: Be careful, you should use the \PH7\Framework\Mvc\Request\HttpRequest::ONLY_XSS_CLEAN constant otherwise the post method of the HttpRequest class removes the tags special
                // and damages the SET function SQL for entry into the database.
                foreach($this->httpRequest->post('category_id', HttpRequest::ONLY_XSS_CLEAN) as $iCategoryId)
                    $oNoteModel->addCategory($iCategoryId, $iNoteId, $iProfileId);

                /* Clean NoteModel Cache */
                (new Framework\Cache\Cache)->start(NoteModel::CACHE_GROUP, null, null)->clear();

                $this->sMsg = ($iApproved == '0') ? t('Your Note has been received! But it will be visible once approved by our moderators. Please do not send a new Note because this is useless!') : t('Post created successfully!');
            }
            HeaderUrl::redirect(UriRoute::get('note','main','read',$this->session->get('member_username') .','. $this->httpRequest->post('post_id')), $this->sMsg);
        }
    }

}

