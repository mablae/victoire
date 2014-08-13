<?php

namespace Victoire\Bundle\WidgetBundle\Controller;

use AppVentus\Awesome\ShortcutsBundle\Controller\AwesomeController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Victoire\Bundle\CoreBundle\Entity\View;
use Victoire\Bundle\CoreBundle\Widget\Managers\WidgetManager;
use Victoire\Bundle\WidgetBundle\Entity\Widget;

/**
 * Widget Controller
 *
 */
class WidgetController extends AwesomeController
{
    /**
     * Show a widget
     * @param Widget $widget the widget to show
     * @param Entity $entity the entity
     *
     * @return response
     * @Route("/victoire-dcms/widget/show/{id}/{entity}", name="victoire_core_widget_show", options={"expose"=true}, defaults={"entity": null})
     * @Template()
     * @ParamConverter("id", class="VictoireWidgetBundle:Widget")
     */
    public function showAction(Widget $widget, $entity = null)
    {
        //the response is for the ajax.js from the AppVentus Ajax Bundle
        try {

            if ($this->getRequest()->isXmlHttpRequest()) {

                 $response = new JsonResponse(array(
                     'html' => $this->get('victoire_widget.widget_renderer')->render($widget, $entity),
                     'update' => 'vic-widget-'.$widget->getId().'-container',
                     'success' => false
                 ));
            } else {
                $response = $this->redirect($this->generateUrl('victoire_core_page_show', array('url' => $widget->getView()->getUrl())));
            }
        } catch (\Exception $ex) {
            $response = $this->getJsonReponseFromException($ex);
        }

        return $response;
    }

    /**
     * New Widget
     *
     * @param string         $type   The type of the widget we edit
     * @param View           $view   The view where attach the widget
     * @param string         $slot   The slot where attach the widget
     * @param BusinessEntity $entity The business entity the widget shows on dynamic mode
     *
     * @return response
     *
     * @Route("/victoire-dcms/widget/new/{type}/{view}/{slot}/{entity}", name="victoire_core_widget_new", defaults={"slot":null, "entity":null}, options={"expose"=true})
     * @Template()
     */
    public function newAction($type, $view, $slot = null, $entity = null)
    {
        try {
            $view = $this->get('doctrine.orm.entity_manager')->getRepository('VictoireCoreBundle:View')->findOneById($view);

            if ($entity) {
                $widgetManager = $this->get('widget_manager')->getManager(null, $type);
                $widget = $widgetManager->newWidget($type, $view, $slot);

                $namespace = null;

                if ($entity === 'static' || $entity === '') {
                    $entity = null;
                }

                if ($entity) {
                    $annotationReader = $this->get('victoire_core.annotation_reader');
                    $classes = $annotationReader->getBusinessClassesForWidget($widget);

                    if (!isset($classes[$entity])) {
                        throw new \Exception('The widget type ['.$entity.'] does not exists.');
                    }

                    $namespace = $classes[$entity];
                }

                $form = $this->get('widget_manager')->buildForm($widgetManager, $widget, $entity, $namespace);

                $response = new JsonResponse($this->get('widget_manager')->renderNewForm($form, $widget, $slot, $view, $entity));
            } else {
                $response = new JsonResponse($this->get('widget_manager')->newWidget($type, $slot, $view));
            }
        } catch (\Exception $ex) {
            $response = $this->getJsonReponseFromException($ex);
        }

        return $response;
    }

    /**
     * Create a widget
     *
     * @param string         $type   The type of the widget we edit
     * @param View           $view   The view where attach the widget
     * @param string         $slot   The slot where attach the widget
     * @param BusinessEntity $entity The business entity the widget shows on dynamic mode
     *
     * @return response
     * @Route("/victoire-dcms/widget/create/{type}/{view}/{slot}/{entity}", name="victoire_core_widget_create", defaults={"slot":null, "entity":null, "_format": "json"})
     * @Template()
     */
    public function createAction($type, $view, $slot = null, $entity = null)
    {
        try {
            //services
            $em = $this->getEntityManager();

            $view = $em->getRepository('VictoireCoreBundle:View')->findOneById($view);
            $widgetManager = $this->getWidgetManager();

            $response = new JsonResponse($widgetManager->createWidget($type, $slot, $view, $entity));
        } catch (\Exception $ex) {
            $response = $this->getJsonReponseFromException($ex);
        }

        return $response;
    }

    /**
     * Edit a widget
     * @param Request $request The request
     * @param Widget  $widget  The widget to edit
     * @param string  $type    The type of widget we edit
     * @param Entity  $entity  The Entity
     *
     * @return response
     *
     * @Route("/victoire-dcms/widget/edit/{id}/{entity}", name="victoire_core_widget_edit", defaults={"type": null})
     * @Route("/victoire-dcms/widget/update/{id}/{entity}", name="victoire_core_widget_update", defaults={"entity": null})
     * @Template()
     * @ParamConverter("id", class="VictoireWidgetBundle:Widget")
     */
    public function editAction(Request $request, Widget $widget, $entity = null)
    {
        try {
            $widgetManager = $this->getWidgetManager();
            $response = new JsonResponse($widgetManager->editWidget($request, $widget, $entity));
        } catch (\Exception $ex) {
            $response = $this->getJsonReponseFromException($ex);
        }

        return $response;
    }

    /**
     * Delete a Widget
     * @param Widget $widget The widget to delete
     *
     * @return empty response
     * @Route("/victoire-dcms/widget/delete/{id}", name="victoire_core_widget_delete", defaults={"_format": "json"})
     * @Template()
     * @ParamConverter("id", class="VictoireWidgetBundle:Widget")
     */
    public function deleteAction(Widget $widget)
    {
        try {
            $response = new JsonResponse($this->get('widget_manager')->deleteWidget($widget));
        } catch (\Exception $ex) {
            $response = $this->getJsonReponseFromException($ex);
        }

        return $response;
    }

    /**
     * Update widget positions accross the view. If moved widget is a Reference, ask to detach the view from template
     *
     * @param View $view The view where update widget positions
     *
     * @return response
     * @Route("/victoire-dcms/widget/updatePosition/{view}", name="victoire_core_widget_update_position", options={"expose"=true})
     * @ParamConverter("view", class="VictoireCoreBundle:View")
     */
    public function updatePositionAction(View $view)
    {
        try {
            //the sorted order for the widgets
            $sortedWidgets = $this->getRequest()->request->get('sorted');

            //create a view for the business entity instance if we are currently display an instance for a business entity template
            $view = $this->get('victoire_page.page_helper')->duplicatePagePatternIfPageInstance($view);

            //recompute the order for the widgets
            $this->get('view.widgetMap.builder')->updateWidgetMapOrder($view, $sortedWidgets);

            $response = new JsonResponse(array('success' => true));
        } catch (\Exception $ex) {
            $response = $this->getJsonReponseFromException($ex);
        }

        return $response;
    }

    /**
     * Shortcut for getting the widget manager
     *
     * @return WidgetManager
     */
    protected function getWidgetManager()
    {
        $manager = $this->get('widget_manager');

        return $manager;
    }

    /**
     * Get the json response by the exception and the current user
     *
     * @param \Exception $ex
     *
     * @return JsonResponse
     */
    protected function getJsonReponseFromException(\Exception $ex)
    {
        //services
        $securityContext = $this->get('security.context');
        $logger = $this->get('logger');

        //can we see the debug
        $isDebugAllowed = $securityContext->isGranted('ROLE_VICTOIRE_PAGE_DEBUG');

        //whatever is the exception, we log it
        $logger->error($ex->getMessage());
        $logger->error($ex->getTraceAsString());

        if ($isDebugAllowed) {
            throw $ex;
        } else {
            //translate the message
            $translator = $this->get('translator');

            //get the translated message
            $message = $translator->trans('error_occured', array(), 'victoire');

            $response = new JsonResponse(
                array(
                    'success' => false,
                    'message' => $message
                )
            );
        }

        return $response;
    }
}
