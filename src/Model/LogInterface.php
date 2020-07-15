<?php

namespace JAMS\IthenticateBundle\Model;

interface LogInterface
{
    public const ACTION_LOGIN = 1;
    public const ACTION_UPLOAD = 2;
    public const ACTION_DOCUMENT_STATUS = 3;
    public const ACTION_FOLDER_LIST = 4;
    public const ACTION_FOLDER_ADD = 5;
    public const ACTION_SIMILARITY_REPORT = 6;


    public function getId();

    public function setResponseStatus($responseStatus);

    public function getResponseStatus();

    public function setRequest($request);

    public function getRequest();

    public function setResponse($response);

    public function getResponse();

    public function setRequestDt($requestDt);

    public function getResponseDt();

    public function setResponseDt($responseDt);

    public function getRequestDt();

    public function getUserId();

    public function getAction();

    public function setUserId($userId);

    public function setAction($action);

}
