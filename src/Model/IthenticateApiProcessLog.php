<?php

namespace JAMS\IthenticateBundle\Model;

abstract class IthenticateApiProcessLog implements LogInterface
{
    public static $allAction = [
        LogInterface::ACTION_LOGIN              => 'login',
        LogInterface::ACTION_UPLOAD             => 'upload',
        LogInterface::ACTION_DOCUMENT_STATUS    => 'document_status',
        LogInterface::ACTION_FOLDER_LIST        => 'folder_list',
        LogInterface::ACTION_FOLDER_ADD         => 'folder_add',
        LogInterface::ACTION_SIMILARITY_REPORT  => 'similarity_report',
    ];


    /**
     * @var integer
     */
    protected $id;

    /**
     * @var integer
     */
    protected $userId;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $responseStatus;

    /**
     * @var string
     */
    protected $request;

    /**
     * @var string
     */
    protected $response;

    /**
     * @var \DateTime
     */
    protected $requestDt;

    /**
     * @var \DateTime
     */
    protected $responseDt;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set responseStatus
     *
     * @param string $responseStatus
     * @return IthenticateApiProcessLog
     */
    public function setResponseStatus($responseStatus)
    {
        $this->responseStatus = $responseStatus;

        return $this;
    }

    /**
     * Get responseStatus
     *
     * @return string
     */
    public function getResponseStatus()
    {
        return $this->responseStatus;
    }

    /**
     * Set request
     *
     * @param string $request
     * @return IthenticateApiProcessLog
     */
    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Get request
     *
     * @return string
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set response
     *
     * @param string $response
     * @return IthenticateApiProcessLog
     */
    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Get response
     *
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set requestDt
     *
     * @param \DateTime $requestDt
     * @return IthenticateApiProcessLog
     */
    public function setRequestDt($requestDt)
    {
        $this->requestDt = $requestDt;

        return $this;
    }

    /**
     * Get responseDt
     *
     * @return \DateTime
     */
    public function getResponseDt()
    {
        return $this->responseDt;
    }

    /**
     * Set responseDt
     *
     * @param \DateTime $responseDt
     * @return IthenticateApiProcessLog
     */
    public function setResponseDt($responseDt)
    {
        $this->responseDt = $responseDt;

        return $this;
    }

    /**
     * Get requestDt
     *
     * @return \DateTime
     */
    public function getRequestDt()
    {
        return $this->requestDt;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    public function getAllAction()
    {
        return self::$allAction;
    }

    public function getActionName()
    {
        return self::$allAction[$this->action];
    }


}
