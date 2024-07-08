<?php
namespace OCA\Files\Controller;

use OCP\IRequest;
use OCP\IDBConnection;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSNotFoundException;

use OCP\IUserSession;
use OCP\IGroupManager;

class PwdController extends Controller
{
    private $db;

    protected $userSession;

    protected $groupManager;

    public function __construct(
        $AppName,
        IRequest $request,
        IDBConnection $db,
        IUserSession $userSession,
        IGroupManager $groupManager
    ) {
        parent::__construct($AppName, $request, $userSession, $groupManager);
        $this->db = $db;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    /**
     * @NoCSRFRequired
     */
    public function createPwd(
        $id,
        $name,
        $pass
    ) {
        $query = $this->db->getQueryBuilder();
        $query
            ->insert("ww_file")
            ->values([
                "id" => $query->createNamedParameter($id),
                "name" => $query->createNamedParameter($name),
                "pass" => $query->createNamedParameter($pass),
            ])
            ->execute();

        return new JSONResponse(["status" => "success"]);
    }

    /**
     * @NoCSRFRequired
     */
    public function getPwd($name)
    {
        $query = $this->db->getQueryBuilder();

        $query
            ->select("*")
            ->from("ww_file")
            ->where(
                $query
                    ->expr()
                    ->eq("name", $query->createNamedParameter($name))
            );

        $result = $query->execute();
        $bonuses = $result->fetchAll();
        return ["status" => "success"];
    }


}
