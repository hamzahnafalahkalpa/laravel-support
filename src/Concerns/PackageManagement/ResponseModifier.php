<?php

namespace Hanafalah\LaravelSupport\Concerns\PackageManagement;

trait ResponseModifier
{
    /** @var array */
    protected static array $__messages = [];
    protected static string $__status = 'success';

    //GETTER SECTION

    /**
     * Retrieves the results of the current instance.
     *
     * @return object The results of the current instance.
     */
    public function result(): object
    {
        return (object) [
            'status'      => self::$__status,
            'data'        => (object) [
                'classes'   => $this->getClasses()
            ],
            'messages'    => $this->getMessages()
        ];
    }

    /**
     * Retrieves the messages stored in the SetupManagement instance.
     *
     * @return array The messages stored in the SetupManagement instance.
     */
    public function getMessages(): array
    {
        return self::$__messages;
    }
    //END GETTER SECTION

    //SETTER SECTION

    /**
     * Adds a message to the internal messages array and returns the current instance.
     *
     * @param string $message The message to be added.
     * @return self The current instance of the class.
     */
    public function pushMessage(string $message): self
    {
        self::$__messages[] = $message;
        return $this;
    }
    //END SETTER SECTION
}
