<?php

namespace CustomBundle\Controller;

use Application\Sonata\MediaBundle\Entity\Media;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MediaController extends Controller
{
    //выгружает media по $id из таблицы media__media, из папки /web/uploads/media/,
    public function downloadMediaFileAction($id, $token)
    {
        if(!self::checkToken($token))
            return new Response(json_encode([
                'error' => 'token error',
            ]), 400, array(
                'Content-Type' => 'application/json',
            ));

        $media = $this->getDoctrine()
            ->getRepository(Media::class)
            ->findBy([
                'id' => $id
            ]);

        if(!count($media))
        {
            return new Response(json_encode([
                'error' => 'media_id:'. $id .' does not exist in DB',
            ]), 404, array(
                'Content-Type' => 'application/json',
            ));
        }

        $provider = $this->container->get($media[0]->getProviderName());
        $absoluteFilepath =  $provider->getFilesystem()->getAdapter()->getDirectory() . "/" .
            $provider->generatePath($media[0]) . "/" .
            $media[0]->getProviderReference();
        $filename = $media[0]->getName().'.'.explode('.', $media[0]->getProviderReference())[1];

        if(!file_exists($absoluteFilepath))
        {
            return new Response(json_encode([
                'error' => 'media_id:'. $id .' does not exist in directory ' . $absoluteFilepath,
            ]), 404, array(
                'Content-Type' => 'application/json',
            ));
        }

        return new Response(file_get_contents($absoluteFilepath),
            200,
            array(
                'Content-Type' => $media[0]->getContentType(),
                'Content-Length' => filesize($absoluteFilepath),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ));
    }

    //удаляет media по $id из таблиц media__media, registries_medias, и из папки /web/uploads/media/
    public function deleteMediaAction($id, $token)
    {
        if(!self::checkToken($token))
            return new Response(json_encode([
                'error' => 'token error',
            ]), 400, array(
                'Content-Type' => 'application/json',
            ));

        $media = $this->getDoctrine()
            ->getRepository(Media::class)
            ->findBy([
                'id' => $id
            ]);

        if(count($media))
        {
            $provider = $this->container->get($media[0]->getProviderName());
            $absoluteFilepath =  $provider->getFilesystem()->getAdapter()->getDirectory() . "/" .
                $provider->generatePath($media[0]) . "/" .
                $media[0]->getProviderReference();

            $em = $this->getDoctrine()->getManager();
            $media = $em->getReference('CustomBundle:RegistriesMedias', $id);
            $em->remove($media);
            $media = $em->getReference('ApplicationSonataMediaBundle:Media', $id);
            $em->remove($media);
            $em->flush();

            if(file_exists($absoluteFilepath)) unlink($absoluteFilepath);
        }
        else
        {
            return new Response(json_encode([
                'error' => 'media_id:'. $id .' does not exist in DB',
            ]), 404, array(
                'Content-Type' => 'application/json',
            ));
        }

        return new Response(json_encode([
            'delete_media_id' => $id,
        ]), 200, array(
            'Content-Type' => 'application/json',
        ));

    }

    //создает media в таблице media__media и в папке /web/uploads/media/
    //$context влияет на название папки в /web/uploads/media/$context
    //$fileName название файла без расширения
    public function createMediaAction(Request $request)
    {
        $media = $request->files->get('media');
        $context = $request->request->get('context');
        $fileName = $request->request->get('file_name');
        $token = $request->request->get('token');

        if(!$media || !$context || !$fileName || !$token)
            return new Response(json_encode([
                'error' => 'not all parameters passed',
            ]), 400, array(
                'Content-Type' => 'application/json',
            ));
        if(!self::checkToken($token))
            return new Response(json_encode([
                'error' => 'token error',
            ]), 400, array(
                'Content-Type' => 'application/json',
            ));

        $mediaManager = $this->container->get('sonata.media.manager.media');
        $media = Media::create(
            $context,
            'sonata.media.provider.file',
            $media->getRealPath(),
            $fileName
        );
        $mediaManager->save($media);

        return new Response(json_encode([
            'create_media_id' => $media->getId(),
        ]), 200, array(
            'Content-Type' => 'application/json',
        ));
    }

    private function checkToken($token)
    {
        //$token находится /app/config/parameters.yml.dist (parameters.yml)
        return $token == $this->getParameter('media_file_token') ? true : false;
    }
}
