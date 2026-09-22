<?php
namespace skeeks\cms\search\controllers;

use skeeks\cms\search\services\StorefrontSuggest;
use skeeks\cms\search\services\SuggestQuery;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

class SuggestController extends Controller
{
    public function behaviors()
    {
        return ['verbs' => ['class' => VerbFilter::class, 'actions' => ['index' => ['GET'], 'page' => ['GET']]]];
    }

    public function actionIndex()
    {
        $response = \Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $query = SuggestQuery::normalize(\Yii::$app->request->get('q', ''));
        $service = new StorefrontSuggest();
        $groups = $service->search($query);
        // Also release an already-open session for empty/non-shop requests.
        if (\Yii::$app->has('session') && \Yii::$app->session->isActive) {
            \Yii::$app->session->close();
        }
        return ['query' => $query, 'matchedQuery' => $service->matchedQuery,
            'highlightQuery' => implode(' ', array_merge([$query], SuggestQuery::alternatives($query))), 'groups' => $groups];
    }

    public function actionPage()
    {
        $request = \Yii::$app->request;
        $group = $request->get('group');
        $page = filter_var($request->get('page', 1), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>100000]]);
        if (!is_string($group) || !in_array($group, ['sections','filters','brands','collections'], true) || !$page) {
            throw new \yii\web\BadRequestHttpException('Invalid search page');
        }
        \Yii::$app->response->format = Response::FORMAT_JSON;
        \Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        \Yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        if (\Yii::$app->session->isActive) {
            \Yii::$app->session->close();
        }
        return (new StorefrontSuggest())->page(SuggestQuery::normalize($request->get('q', '')), $group, $page);
    }
}
