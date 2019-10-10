<?php

use Respect\Validation\Validator as DataValidator;
DataValidator::with('CustomValidations', true);

/**
 * @api {post} /user/invite Invite user
 * @apiVersion 4.5.0
 *
 * @apiName Invite user
 *
 * @apiGroup User
 *
 * @apiDescription This path allows a staff member to invite a user on the system.
 *
 * @apiPermission any
 *
 * @apiParam {String} name The name of the new user.
 * @apiParam {String} email The email of the new user.
 * @apiParam {String} apiKey APIKey to sign up a user if the registration system is disabled.
 * @apiParam {String} customfield_ Custom field values for this user.
 *
 * @apiUse INVALID_NAME
 * @apiUse INVALID_EMAIL
 * @apiUse INVALID_CAPTCHA
 * @apiUse USER_SYSTEM_DISABLED
 * @apiUse USER_EXISTS
 * @apiUse ALREADY_BANNED
 * @apiUse NO_PERMISSION
 * @apiUse INVALID_CUSTOM_FIELD_OPTION
 *
 * @apiSuccess {Object} data Information about created user
 * @apiSuccess {Number} data.userId Id of the new user
 * @apiSuccess {String} data.userEmail Email of the new user
 *
 */

class InviteController extends Controller {
    const PATH = '/invite';
    const METHOD = 'POST';

    private $userEmail;
    private $userName;
    private $verificationToken;
    private $invitationToken;

    public function validations() {
        $validations = [
            'permission' => 'any',
            'requestData' => [
                'name' => [
                    'validation' => DataValidator::length(2, 55),
                    'error' => ERRORS::INVALID_NAME
                ],
                'email' => [
                    'validation' => DataValidator::email(),
                    'error' => ERRORS::INVALID_EMAIL
                ]
            ]
        ];

        $validations['requestData']['captcha'] = [
            'validation' => DataValidator::captcha(),
            'error' => ERRORS::INVALID_CAPTCHA
        ];

        return $validations;
    }

    public function handler() {
        if(!Controller::isUserSystemEnabled()) {
            throw new RequestException(ERRORS::USER_SYSTEM_DISABLED);
        }

        $this->storeRequestData();
        $apiKey = APIKey::getDataStore(Controller::request('apiKey'), 'token');

        $existentUser = User::getUser($this->userEmail, 'email');

        if (!$existentUser->isNull()) {
            throw new RequestException(ERRORS::USER_EXISTS);
        }
        $banRow = Ban::getDataStore($this->userEmail,'email');

        if (!$banRow->isNull()) {
            throw new RequestException(ERRORS::ALREADY_BANNED);
        }

        if (!Setting::getSetting('registration')->value && $apiKey->isNull() && !Controller::isStaffLogged(2)) {
            throw new RequestException(ERRORS::NO_PERMISSION);
        }

        $userId = $this->createNewUserAndRetrieveId();

        if(MailSender::getInstance()->isConnected()) {
            $this->sendInvitationMail();
        }

        Response::respondSuccess([
            'userId' => $userId,
            'userEmail' => $this->userEmail
        ]);

        Log::createLog('SIGNUP', null, User::getDataStore($userId));
    }

    public function storeRequestData() {
        $this->userName = Controller::request('name');
        $this->userEmail = Controller::request('email');
        $this->verificationToken = Hashing::generateRandomToken();
    }

    public function createNewUserAndRetrieveId() {
        $userInstance = new User();

        $userInstance->setProperties([
            'name' => $this->userName,
            'signupDate' => Date::getCurrentDate(),
            'tickets' => 0,
            'email' => $this->userEmail,
            'password' => Hashing::hashPassword($this->userPassword),
            'verificationToken' => (MailSender::getInstance()->isConnected()) ? $this->verificationToken : null,
            'invitationToken' => (Mail)
            'xownCustomfieldvalueList' => $this->getCustomFieldValues()
        ]);

        return $userInstance->store();
    }

    public function sendInvitationMail() {
        $mailSender = MailSender::getInstance();

        $mailSender->setTemplate(MailTemplate::USER_SIGNUP, [
            'to' => $this->userEmail,
            'name' => $this->userName,
            'url' => Setting::getSetting('url')->getValue(),
            'verificationToken' => $this->verificationToken
        ]);

        $mailSender->send();
    }
}
