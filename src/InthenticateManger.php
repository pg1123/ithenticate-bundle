<?php
namespace JAMS\IthenticateBundle;

use GuzzleHttp\Client;
use MDPI\CoreBundle\Entity\JournalRepository;
use MDPI\CoreBundle\Entity\SubmissionManuscript;
use MDPI\CoreBundle\Entity\User;
use JAMS\CoreBundle\Entity\IthenticateApiProcessUploadRepository;
use MDPI\CoreBundle\Entity\IthenticateApiDocRepository;
use MDPI\CoreBundle\Entity\IthenticateApiProcessUpload;

use Twig\Environment;
use JAMS\IthenticateBundle\Entity\IthenticateApiProcessLog;
use JAMS\IthenticateBundle\Model\LogInterface;
/**
 * Ithenticate API Service
 * @package Ithenticate
 *
 */
class InthenticateManger
{
    const MAX_RETRIES = 3;
    private $container;
    private $templating;
    private $user;
    private $sid;
    private $folderId;
    private $docId;
    private $reportId;
    private $filePath;
    private $uploadParameters = [];
    private $retries = 0;

    private $ithenticateUrl;
    private $ithenticateEmail;
    private $ithenticatePassword;
    private $ithenticateGroupId;
    private $dmsDir;
    private $twig;
    private $logger;
    private $doctrine;

    public function __construct($managerOptions,Environment $twig, $logger, $doctrine)
    {
        $this->ithenticateUrl = $managerOptions['url'];
        $this->ithenticateEmail = $managerOptions['email'];
        $this->ithenticatePassword = $managerOptions['password'];
        $this->ithenticateGroupId = $managerOptions['group_folder_id'];
        $this->dmsDir = $managerOptions['dms_dir'];
        $this->twig = $twig;
        $this->logger = $logger;
        $this->doctrine = $doctrine;
    }

    /**
     * Set user
     *
     * @param User $user
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set manuscript
     *
     * @param SubmissionManuscript $manuscript
     * @return $this
     */
    public function setManuscript(SubmissionManuscript $manuscript)
    {
        $this->manuscript = $manuscript;

        return $this;
    }

    /**
     * Get upload file path
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Set manuscript file path
     *  $uploadXml = $this->templating->render('MDPIMainBundle:IthenticateRequest:submit_
     */
    public function setManuscriptFilePath(SubmissionManuscript $manuscript)
    {
        $dmsDir = $this->dmsDir;

        // peer-review pdf > author uploaded pdf > author uploaded word
        $peerReviewFile = $manuscript->getPeerReviewFile();
        $presentationFile = $manuscript->getArticlePresentation();
        $articleFileWord = $manuscript->getArticleFile();
        $filePath = $manuscript->getFilePath($dmsDir, $peerReviewFile);
        if (!is_file($filePath)) {
            $filePath = $manuscript->getFilePath($dmsDir, $presentationFile);
        }
        if (!is_file($filePath)) {
            $filePath = $manuscript->getFilePath($dmsDir, $articleFileWord);
        }
        $this->filePath = $filePath;

        return $this;
    }

    /**
     * Login
     *
     * @return array
     */
    public function login()
    {
        $loginXml = $this->twig->render(
            '@JAMSIthenticate/IthenticateRequest/login.xml.twig',
            [
                'username' => $this->ithenticateEmail,
                'password' => $this->ithenticatePassword,
            ]
        );
        $loginResult = $this->apiRequest($loginXml, LogInterface::ACTION_LOGIN);
        print_r($loginResult);exit;
        if ($loginResult && !empty($loginResult['data']['sid'])) {
            $this->sid          = $loginResult['data']['sid'];
        }
        return $loginResult;
    }

    /**
     * iThenticate API request
     *
     * @param string $xml
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function apiRequest($xml, $action)
    {
        $userId = $this->user ? $this->user->getId() : 0;
        $msId = null;
        $ithenticateApiProcessUpload = null;
        $ithenticateApiProcessLog = null;
        if (LogInterface::ACTION_LOGIN === $action) {
            $params = $this->uploadParameters;
            $params['upload_file_content'] = $this->filePath;
            $shortXml = $this->getRequestXml($action, $params);
            if (!empty($this->manuscript)) {
                $msId = $this->manuscript->getMsId();
                $ithenticateApiProcessUpload = IthenticateApiProcessUploadRepository::getOrCreateForManuscript($this->manuscript->getHashKey(), $userId);
            } elseif (!empty($this->englishArticle)) {
                $msId = $this->englishArticle->getFormattedId();
                $ithenticateApiProcessUpload = IthenticateApiProcessUploadRepository::getOrCreateForEnglish($this->englishArticle->getId(), $userId);
            }
            if (empty($ithenticateApiProcessUpload)) {
                $errorMessage = 'Manuscript or English pre-edit upload log can not be found.';
                $errorCode = 404;
                $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s", $msId, $errorCode, $errorMessage));
                return [
                    'status' => 'ERROR',
                    'message' => $errorMessage,
                    'code' => $errorCode,
                ];
            }
            IthenticateApiProcessUploadRepository::updateWithFields([
                'request' => $shortXml,
            ], [
                'id' => $ithenticateApiProcessUpload->getId(),
            ]);
        } else {
            /*$processLogId = IthenticateApiProcessLogRepository::insertWithFields([
                'user_id' => $userId,
                'action' => $action,
                'request_dt' => IthenticateApiProcessLogRepository::now()
            ]);*/
            $processLog = new IthenticateApiProcessLog();
            $processLog->setUserId($userId);
            $processLog->setAction($action);
            $processLog->setRequestDt(date('Y-m-d h:i:s', time()));
            $em = $this->doctrine->getManager();
            $em->persist($processLog);
            $em->flush();
            echo 222;exit;



            $ithenticateApiProcessLog = IthenticateApiProcessLogRepository::getOneById($processLogId);
            if (empty($ithenticateApiProcessLog)) {
                $errorMessage   = 'iThenticate API process log can not be found.';
                $errorCode      = 404;
                $this->logger->error(sprintf("[ITHENTICATE-ENTRY] Action: %s, Code: %d, Message: %s", $action, $errorCode, $errorMessage));
                return [
                    'status'    => 'ERROR',
                    'message'   => $errorMessage,
                    'code'      => $errorCode,
                ];
            }
            IthenticateApiProcessLogRepository::updateWithFields(
                ['request' => $xml],
                ['id' => $ithenticateApiProcessLog->getId()]
            );
        }

        try {
            $client = new Client();
            $options = [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF8',
                ],
                'body' => $xml,
            ];
            $responseData = $client->request('POST', $this->ithenticateUrl, $options);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
            $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s", $msId, $errorCode, $errorMessage));
            return [
                'status' => 'ERROR',
                'message' => $errorMessage,
                'code' => $errorCode,
            ];
        }

        $responseXml = (string)$responseData->getBody();
        $response = xmlrpc_decode($responseXml);
        $apiStatus = !empty($response['api_status']) ? (int)$response['api_status'] : null;
        $code = !empty($response['code']) ? (int)$response['code'] : null;

        if (200 !== $responseData->getStatusCode()) {
            $errorMessage = 'Wrong Response HTTP status.';
            $errorCode = $responseData->getStatusCode();
            return [
                'status' => 'ERROR',
                'message' => $errorMessage,
                'code' => $errorCode,
            ];
        }

        if (LogInterface::ACTION_UPLOAD === $action) {
            IthenticateApiProcessUploadRepository::updateWithFields([
                'response' => $responseXml,
                'response_status' => $apiStatus,
                'status_dt' => IthenticateApiProcessUploadRepository::now(),
            ], [
                'id' => $ithenticateApiProcessUpload->getId(),
            ]);
        } else {
            IthenticateApiProcessLogRepository::updateWithFields([
                'response' => $responseXml,
                'response_status' => $apiStatus,
                'response_dt' => IthenticateApiProcessLogRepository::now(),
            ], [
                'id' => $ithenticateApiProcessLog->getId(),
            ]);
        }

        if ((401 === $code || 401 === $apiStatus) && $this->retries <= self::MAX_RETRIES) {
            $this->login();
            $this->retries++;
            return $this->apiRequest($xml, $action);
        }

        if (empty($apiStatus) || !empty($response['faultCode'])) {
            $errorMessage = 'iThenticate response status is invalid.';
            $errorCode = !empty($response['faultCode']) ? $response['faultCode'] : $apiStatus;
            $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s, Info: %s", $msId, $errorCode, $errorMessage, $responseXml));
            return array(
                'status' => 'ERROR',
                'message' => $errorMessage,
                'code' => $errorCode,
            );
        }

        if (200 === $apiStatus) {
            if (LogInterface::ACTION_UPLOAD === $action) {
                IthenticateApiProcessUploadRepository::updateWithFields(array(
                    'session_id' => $response['sid'],
                    'doc_id' => $response['uploaded'][0]['id'],
                ), array(
                    'id' => $ithenticateApiProcessUpload->getId(),
                ));

                $ithenticateApiProcessDoc = IthenticateApiDocRepository::getOrCreateProcessDoc($ithenticateApiProcessUpload->getId());
                if (empty($ithenticateApiProcessDoc)) {
                    $errorMessage = 'iThenticate API process doc is not found.';
                    $errorCode = 404;
                    $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s, Upload id: %s", $msId, $errorCode, $errorMessage, $ithenticateApiProcessUpload->getId()));
                    return array(
                        'status' => 'ERROR',
                        'message' => $errorMessage,
                        'code' => $errorCode,
                    );
                }
            }

            return array(
                'status' => 'OK',
                'message'=> 'Process success.',
                'code' => $apiStatus,
                'data' => $response,
            );
        } else {
            if (isset($response["messages"])) {
                if (is_array($response["messages"])) {
                    $errorMessage = implode("\n<br>,", $response["messages"]);
                } else {
                    $errorMessage =  $response["messages"];
                }
            } else {
                $errorMessage = "Unknown server error.";
            }
            $errorCode = $apiStatus;
            $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s, Info: %s", $msId, $errorCode, $errorMessage, $responseXml));
            return array(
                'status'    => 'ERROR',
                'message'   => $errorMessage,
                'code'      => $errorCode,
            );
        }
    }

    /**
     * Upload manuscript file to iThenticate
     *
     * @param SubmissionManuscript $manuscript
     * @return array
     */
    public function uploadManuscriptFile(SubmissionManuscript $manuscript)
    {
        $this->setManuscript($manuscript)->setManuscriptFilePath($manuscript);
        $journal = $manuscript->getJournal();
        $this->folderId = $journal->getIthenticateFolderId();
        if (!$this->folderId) {
            $this->createFolder($journal->getNameSystem());
        }
        $filePath                   = $this->getFilePath();
        if (!file_exists($filePath)) {
            return array(
                'status'    => 'ERROR',
                'message'   => 'there is no file to upload ...',
            );
        }
        $fileContent                = file_get_contents($filePath);
        $fileExt                    = pathinfo($filePath, PATHINFO_EXTENSION);
        $uploadFileContent          = base64_encode($fileContent);
        $filename                   = $manuscript->getMsId() . '.' . $fileExt;
        $author                     = $manuscript->getArticleAuthor();

        $this->uploadParameters     = array(
            'sid'                   => $this->sid,
            'author_firstname'      => $author ? $author->getFirstname() : 'Anonymous',
            'author_lastname'       => $author ? $author->getLastname() : 'Anonymous',
            'title'                 => $manuscript->getArticleTitle(),
            'folder'                => $this->folderId,
            'filename'              => $filename,
            'submit_to'             => 1,
            'upload_file_content'   => $uploadFileContent,
        );
        return $this->uploadFile($manuscript->getMsId());
    }

    /**
     * Upload file to iThenticate
     *
     * @param string $msId
     * @return array
     */
    public function uploadFile($msId)
    {
        $filePath           = $this->filePath;
        if (!is_file($filePath)) {
            $errorMessage   = 'PDF / Word (docx) file of the manuscript missing. Please upload a PDF / Word (docx) file and refresh the page in order to be able to upload the manuscript to iThenticate.';
            $errorCode      = 404;
            $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s, Info: %s", $msId, $errorCode, $errorMessage, $filePath));
            return array(
                'status'    => 'ERROR',
                'message'   => $errorMessage,
                'code'      => $errorCode,
            );
        }

        $fileExt = pathinfo($filePath, PATHINFO_EXTENSION);
        $fileExt = strtolower($fileExt);
        if (!in_array($fileExt, array('pdf', 'docx', 'doc'))) {
            $errorMessage   = 'Wrong file extension! Please upload a PDF (.pdf) / Word (.docx) file and refresh the page in order to be able to upload the manuscript to iThenticate.';
            $errorCode      = 404;
            $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s, Info: %s", $msId, $errorCode, $errorMessage, $fileExt));
            return array(
                'status'    => 'ERROR',
                'message'   => $errorMessage,
                'code'      => $errorCode,
            );
        }

        if (filesize($filePath) > 41943040) {
            $errorMessage   = 'The file is too large. Allowed maximum size is 40MB.';
            $errorCode      = 413;
            $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s, Info: %s", $msId, $errorCode, $errorMessage, filesize($filePath)));
            return array(
                'status'    => 'ERROR',
                'message'   => $errorMessage,
                'code'      => $errorCode,
            );
        }

        $pdfFileContent     = file_get_contents($filePath);

        if (mb_strlen($pdfFileContent) < 20) {
            $errorMessage   = 'File must contain at least 20 words of text.';
            $errorCode      = 404;
            $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s, Info: %s", $msId, $errorCode, $errorMessage, mb_strlen($pdfFileContent)));
            return array(
                'status'    => 'ERROR',
                'message'   => $errorMessage,
                'code'      => $errorCode,
            );
        }

        $uploadXml          = $this->twig->render(
            '@JAMSIthenticate/IthenticateRequest/submit_document.xml.twig',
            $this->uploadParameters
        );
        return $this->apiRequest($uploadXml, LogInterface::ACTION_UPLOAD);
    }

    /**
     * Get folder list
     *
     * @return array
     */
    public function getFolderList()
    {
        $action = LogInterface::ACTION_FOLDER_LIST;
        $folderListResult       = $this->apiRequest($this->getRequestXml($action), $action);

        if ($folderListResult && !empty($folderListResult['folders'][0]['id'])) {
            $this->folderId     = $folderListResult['folders'][0]['id'];
        }

        return $folderListResult;
    }

    /**
     * Create folder
     * @return int
     */
    public function createFolder($name)
    {
        $params = [
            'sid' => $this->sid,
            'name' => $name,
            'folder_group' => $this->folder_group,
        ];

        $uploadXml = $this->twig->render(
            '@JAMSIthenticate/IthenticateRequest/folder_add.xml.twig',
            $params
        );

        $res = $this->apiRequest($uploadXml, LogInterface::ACTION_FOLDER_ADD);
        if ($this->folderId = $res['data']['id']) {
            JournalRepository::updateWithFields(
                [
                    'ithenticate_folder_id' => $this->folderId
                ],
                [
                    'name_system' => $name
                ]
            );
        }
        return $this->folderId;
    }

    /**
     * Get request xml
     *
     * @param string $action
     * @param array $params
     * @return string|null
     */
    private function getRequestXml($action, $params = [])
    {

        switch ($action) {
            case LogInterface::ACTION_LOGIN:
                $xml = $this->twig->render(
                    '@JAMSIthenticate/IthenticateRequest/login.xml.twig',
                    [
                        'username' => $this->ithenticateEmail,
                        'password' => $this->ithenticatePassword,
                    ]
                );
                break;
            case LogInterface::ACTION_FOLDER_LIST:
                $xml = $this->twig->render(
                    '@JAMSIthenticate/IthenticateRequest/folder_list.xml.twig',
                    [
                        'sid'=> $this->sid,
                    ]
                );
                break;
            case LogInterface::ACTION_DOCUMENT_STATUS:
                $xml = $this->twig->render(
                    '@JAMSIthenticate/IthenticateRequest/document_status.xml.twig',
                    [
                        'sid' => $this->sid,
                        'doc_id' => $this->docId,
                    ]
                );
                break;
            case LogInterface::ACTION_SIMILARITY_REPORT:
                $xml = $this->twig->render(
                    '@JAMSIthenticate/IthenticateRequest/similarity_report.xml.twig',
                    [
                        'sid' => $this->sid,
                        'report_id' => $this->reportId,
                    ]
                );
                break;
            case LogInterface::ACTION_UPLOAD:
                $xml = $this->twig->render(
                    '@JAMSIthenticate/IthenticateRequest/submit_document.xml.twig',
                    $params ?: $this->uploadParameters
                );
                break;
            default:
                $xml = null;
                break;
        }

        return $xml;
    }

    /**
     * Get manuscript iThenticate report result
     *
     * @return array
     */
    public function getManuscriptIthenticateResult($updateReport = false)
    {
        $data = array();
        if (empty($this->manuscript)) {
            $errorMessage   = 'Manuscript can not be found.';
            $errorCode      = 404;
            return array(
                'status'    => 'ERROR',
                'message'   => $errorMessage,
                'code'      => $errorCode,
                'data'      => $data,
            );
        }
        $uploadResult       = IthenticateApiProcessUploadRepository::getOneByHashKey($this->manuscript->getHashKey());
        if (empty($uploadResult)) {
            $errorMessage   = 'Manuscript upload log can not be found.';
            $errorCode      = 404;
//            $this->logger->error(sprintf("[ITHENTICATE-ENTRY] MS-ID: %s, Code: %d, Message: %s", $this->manuscript->getMsId(), $errorCode, $errorMessage));
            return array(
                'status'    => 'ERROR',
                'message'   => $errorMessage,
                'code'      => $errorCode,
                'data'      => $data,
            );
        }

        return $this->getIthenticateResult($uploadResult, $updateReport);
    }

    /**
     * Get iThenticate report result by API upload log
     *
     * @param IthenticateApiProcessUpload $uploadResult
     * @return array
     */
    private function getIthenticateResult(IthenticateApiProcessUpload $uploadResult, $updateReport = false)
    {
        $data  = $uploadResult->getIthenticateData();
        $this->docId = $data['doc_id'];
        $this->reportId = $data['report_id'];
        if (empty($data['report_id']) || 1 == $data['is_pending']) {
            $docId  = $data['doc_id'];
            $result = $this->getDocumentStatus($docId);
            if ('OK' === $result['status'] && $resultData = $result['data']) {
                if (empty($resultData['documents'][0])) {
                    $errorMessage = 'No document found';
                    $errorCode = 500;
                    $this->logger->error(sprintf("[ITHENTICATE-ENTRY] Doc ID: %d, Code: %d, Message: %s", $docId, $errorCode, $errorMessage));
                    return array(
                        'status' => 'ERROR',
                        'message' => $errorMessage,
                        'code' => $errorCode,
                        'data' => $data,
                    );
                }
                $document = $resultData['documents'][0];
                if (!empty($document['error'])) {
                    $errorMessage = $document['error'];
                    $errorCode = 500;
                    $this->logger->error(sprintf("[ITHENTICATE-ENTRY] Doc ID: %d, Code: %d, Message: %s", $docId, $errorCode, $errorMessage));
                    return array(
                        'status' => 'ERROR',
                        'message' => $errorMessage,
                        'code' => $errorCode,
                        'data' => $data,
                    );
                }
                $newData = array(
                    'is_pending' => (int)$document['is_pending'],
                    'percent_match' => (int)$document['percent_match'],
                    'report_id' => !empty($document['parts'][0]['id']) ? (int)$document['parts'][0]['id'] : 0,
                );
                IthenticateApiDocRepository::updateWithFields($newData, array(
                    'id' => $data['api_process_doc_id'],
                ));
                $data = array_merge($data, $newData);
            }
        } elseif ($updateReport && $this->reportId) {
            if (empty($data['view_url']) || empty($data['view_only_expires']) || strtotime($data['view_only_expires']) < time()) {
                $newData = [];
                // Refresh document percent_match data
                if ($this->docId && ($docResult = $this->getDocumentStatusResult()) && !empty($docResult['percent_match'])) {
                    $newData['percent_match'] = (int)$docResult['percent_match'];
                }
                $result = $this->getSimilarityReport($this->reportId);
                if ('OK' === $result['status'] && $resultData = $result['data']) {
                    $newData = array_merge($newData, [
                        'view_url' => $resultData['view_only_url'],
                        'view_only_expires' => date('Y-m-d H:i:s', $resultData['view_only_expires']->timestamp),
                    ]);
                    if (!empty($newData)) {
                        IthenticateApiDocRepository::updateWithFields($newData, array(
                            'id' => $data['api_process_doc_id'],
                        ));
                        $data = array_merge($data, $newData);
                    }
                }
            }
        }
        return [
            'status' => 'OK',
            'message' => 'iThenticate result found.',
            'code' => 200,
            'data' => $data,
        ];
    }

    /**
     * Get document status
     *
     * @param int $docId
     * @return array
     */
    public function getDocumentStatus()
    {
        $documentXml = $this->twig->render(
            '@JAMSIthenticate/IthenticateRequest/document_status.xml.twig',
            array(
                'sid' => $this->sid,
                'doc_id' => $this->docId,
            )
        );

        return $this->apiRequest($documentXml, LogInterface::ACTION_DOCUMENT_STATUS);
    }

    /**
     * Get document status result
     *
     * @return array
     */
    public function getDocumentStatusResult()
    {
        $result = $this->getDocumentStatus();
        if ('OK' === $result['status'] && !empty($result['data']['documents'][0])) {
            $document = $result['data']['documents'][0];
            if (empty($document['error'])) {
                return $document;
            }
        }

        return [];
    }

    /**
     * Get similarity report
     *
     * @param int $reportId
     * @return array
     */
    public function getSimilarityReport($reportId)
    {
        $reportXml = $this->twig->render(
            '@JAMSIthenticate/IthenticateRequest/similarity_report.xml.twig',
            array(
                'sid' => $this->sid,
                'report_id' => $reportId,
            )
        );

        return $this->apiRequest($reportXml, LogInterface::ACTION_SIMILARITY_REPORT);
    }
}
