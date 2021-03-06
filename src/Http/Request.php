<?php


namespace TNM\USSD\Http;

use Illuminate\Http\Request as BaseRequest;
use TNM\USSD\Models\Session;
use TNM\USSD\Screen;

class Request extends BaseRequest
{
    const INITIAL = 1, RESPONSE = 2;
    public $msisdn;
    public $session;
    public $type;
    /**
     * @var string $message
     */
    public $message;
    /**
     * @var Session
     */
    public $trail;

    /**
     * @var bool $valid whether the request is valid XML document or not
     */
    private $valid = false;

    public function __construct()
    {
        parent::__construct();
        $this->setProperties(resolve(UssdRequestInterface::class));

        if ($this->invalid()) return;

        $this->setSessionLocale();
        $this->trail = $this->getTrail();
    }

    public function toPreviousScreen(): bool
    {
        return $this->message == Screen::PREVIOUS;
    }

    public function toHomeScreen(): bool
    {
        return $this->isInitial() || $this->message == Screen::HOME;
    }

    public function invalid(): bool
    {
        return !$this->valid;
    }

    private function setValid(UssdRequestInterface $request): void
    {
        if (!$request) {
            $this->valid = false;
            return;
        }

        $this->valid = !empty($request->getMsisdn()) &&
            !empty($request->getSession()) &&
            !empty($request->getType()) &&
            !empty($request->getMessage());
    }

    private function setProperties(UssdRequestInterface $request): void
    {
        $this->setValid($request);

        if ($this->valid) {
            $this->msisdn = $request->getMsisdn();
            $this->session = $request->getSession();
            $this->type = $request->getType();
            $this->message = $request->getType();
        }
    }

    private function setSessionLocale(): void
    {
        if (Session::where(['session_id' => $this->session])->doesntExist()) return;

        $session = Session::findBySessionId($this->session);
        app()->setLocale($session->{'locale'});
    }

    public function isInitial(): bool
    {
        return $this->type == self::INITIAL;
    }

    public function isResponse(): bool
    {
        return $this->type == self::RESPONSE;
    }

    private function getTrail(): Session
    {
        return Session::firstOrCreate(
            ['session_id' => $this->session],
            ['state' => 'init', 'msisdn' => $this->msisdn]
        );
    }

    public function getScreen(): Screen
    {
        return new $this->trail->{'state'}($this);
    }

    public function getPreviousScreen(): Screen
    {
        return $this->getScreen()->previous();
    }


}
