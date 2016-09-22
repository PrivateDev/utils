<?php

namespace PrivateDev\Utils\Controller;

use Doctrine\ORM\EntityRepository;
use PrivateDev\Utils\Error\ErrorCodes;
use PrivateDev\Utils\Form\FormErrorAdapter;
use PrivateDev\Utils\Fractal\TransformerAbstract;
use PrivateDev\Utils\Json\TransformableJsonResponseBuilder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class CRUDController extends Controller
{
    const ACTION_CREATE = 1;
    const ACTION_READ = 2;
    const ACTION_UPDATE = 3;
    const ACTION_DELETE = 4;

    /**
     * Get repository of the Entity
     *
     * @return EntityRepository
     */
    abstract protected function getEntityRepository();

    /**
     * Create Form for the Entity
     *
     * @param object $entity
     * @param array  $options
     *
     * @return FormInterface
     */
    abstract protected function createEntityForm($entity, array $options = []) : FormInterface;

    /**
     * Create transformer for the Entity
     *
     * @return TransformerAbstract
     */
    abstract protected function createEntityTransformer();

    /**
     * Create an empty Entity
     *
     * @return object
     */
    abstract protected function createEntity();

    /**
     * @return TransformableJsonResponseBuilder
     */
    abstract protected function getResponseBuilder();

    /**
     * Roles for actions
     *
     * Note: null - no restrictions
     *       true - action restricted
     *
     * @return array
     */
    protected function getRoles()
    {
        return [
            self::ACTION_CREATE => null,
            self::ACTION_READ   => null,
            self::ACTION_UPDATE => null,
            self::ACTION_DELETE => null
        ];
    }

    /**
     * @param int $action
     *
     * @return string|null
     */
    protected function getAccessRole(int $action)
    {
        $roles = $this->getRoles();

        return isset($roles[$action])
            ? $roles[$action]
            : null;
    }

    /**
     * @param object  $entity
     * @param Request $request
     *
     * @return JsonResponse
     */
    protected function doUpdate($entity, Request $request)
    {
        $responseBuilder = $this->getResponseBuilder();

        $form = $this->createEntityForm($entity, ['method' => $request->getMethod()]);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->get('doctrine.orm.entity_manager');
            $em->persist($entity);
            $em->flush($entity);
            $response = $responseBuilder
                ->setTranformableItem($entity, $this->createEntityTransformer())
                ->build();
        } else {
            $response = $responseBuilder
                ->addErrorList(new FormErrorAdapter($form->getErrors(true), ErrorCodes::VALIDATION_ERROR))
                ->build(JsonResponse::HTTP_BAD_REQUEST);
        }

        return $response;
    }

    /**
     * @param int    $action
     * @param object $entity
     */
    protected function postEntityLoadCheckAccess($action, $entity)
    {
        // By default do nothing, but you can override it
    }

    /**
     * @Route()
     * @Method({"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function createAction(Request $request)
    {
        $role = $this->getAccessRole(self::ACTION_CREATE);

        if ($role && !$this->isGranted($role)) {
            throw new AccessDeniedHttpException();
        }

        $entity = $this->createEntity();

        return $this->doUpdate($entity, $request);
    }

    /**
     * @param $entity
     *
     * @return JsonResponse
     */
    protected function doRead($entity)
    {
        return $this->getResponseBuilder()
            ->setTranformableItem($entity, $this->createEntityTransformer())
            ->build();
    }

    /**
     * @Route(path="/{id}")
     * @Method({"GET"})
     *
     * @param $id
     *
     * @return Response
     */
    public function readAction($id)
    {
        $role = $this->getAccessRole(self::ACTION_READ);

        if ($role && !$this->isGranted($role)) {
            throw new AccessDeniedHttpException();
        }

        $entity = $this->getEntityRepository()->find($id);

        if (!$entity) {
            throw new NotFoundHttpException();
        }

        $this->postEntityLoadCheckAccess(self::ACTION_READ, $entity);

        return $this->doRead($entity);
    }

    /**
     * @Route(path="/{id}")
     * @Method({"PUT", "PATCH"})
     *
     * @param Request $request
     * @param int     $id
     *
     * @return JsonResponse
     */
    public function updateAction(Request $request, $id)
    {
        $role = $this->getAccessRole(self::ACTION_UPDATE);

        if ($role && !$this->isGranted($role)) {
            throw new AccessDeniedHttpException();
        }

        $entity = $this->getEntityRepository()->find($id);

        if (!$entity) {
            throw new NotFoundHttpException();
        }

        $this->postEntityLoadCheckAccess(self::ACTION_UPDATE, $entity);

        return $this->doUpdate($entity, $request);
    }

    /**
     * @param $entity
     *
     * @return JsonResponse
     */
    protected function doDelete($entity)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $em->remove($entity);
        $em->flush($entity);

        return $this
            ->getResponseBuilder()
            ->build();
    }

    /**
     * @Route("/{id}")
     * @Method({"DELETE"})
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function deleteAction($id)
    {
        $role = $this->getAccessRole(self::ACTION_DELETE);

        if ($role && !$this->isGranted($role)) {
            throw new AccessDeniedHttpException();
        }

        $entity = $this->getEntityRepository()->find($id);

        if (!$entity) {
            throw new NotFoundHttpException();
        }

        $this->postEntityLoadCheckAccess(self::ACTION_DELETE, $entity);

        return $this->doDelete($entity);
    }
}