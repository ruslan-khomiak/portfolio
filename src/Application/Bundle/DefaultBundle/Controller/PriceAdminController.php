<?php

namespace Application\Bundle\DefaultBundle\Controller;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Sonata\AdminBundle\Controller\CoreController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * PriceAdminController.
 */
class PriceAdminController extends CoreController
{
    const JSON_RESULT_APP_FILE_NAME = 'price_app.json';
    const JSON_RESULT_WEB_FILE_NAME = 'price_web.json';

    /**
     * @param Request $request
     * @param string $type
     *
     * @return Response
     */
    public function loadAction(Request $request, $type)
    {
        if (!\in_array($type, ['web', 'app'], true)) {
            throw new BadRequestHttpException('Bat type parameter!');
        }

        $resultFileName = self::JSON_RESULT_APP_FILE_NAME;
        if ('web' === $type) {
            $resultFileName = self::JSON_RESULT_WEB_FILE_NAME;
        }

        $message = '';
        $data = [];
        if ($request->isMethod(Request::METHOD_POST) && $request->files->has('price')) {
            $file = $request->files->get('price');
            $fileConstraint =
                [
                    new NotBlank(),
                    new File(['mimeTypes' => ['text/plain', 'text/csv']]),
                ];
            /** @var $errors \Symfony\Component\Validator\ConstraintViolationList */
            $errors = $this->get('validator')->validate($file, $fileConstraint);
            if ($errors->count() > 0) {
                $message = $errors->get(0)->getMessage();
            } elseif ($file->getClientMimeType() !== 'text/csv') {
                $message = 'Не верный формат файла!';
            } else {
                $config = $this->container->getParameter('application_default.config');
                $uploadDir = $this->container->getParameter('upload_csv_file');
                $path = $config['web_root'].$uploadDir;
                $originalFileName = 'price_original.csv';
                try {
                    $file->move($path, $originalFileName);
                    $data = $this->parseCsvFile($path.'/'.$originalFileName);
                    if (\count($data) > 0) {
                        $resultForFile = $this->get('serializer')->serialize($data, 'json');
                        $fp = \fopen($path.'/'.$resultFileName, 'w');
                        \fwrite($fp, $resultForFile);
                        \fclose($fp);
                    }
                } catch (FileException $e) {
                    $message = $e->getMessage();
                }
            }
        }

        return $this->render(
            '@ApplicationDefault/PriceAdmin/load.html.twig',
            [
                'base_template' => $this->getBaseTemplate(),
                'admin_pool' => $this->container->get('sonata.admin.pool'),
                'blocks' => $this->container->getParameter('sonata.admin.configuration.dashboard_blocks'),
                'form_action' => $this->generateUrl('sonata_admin_price_load'),
                'message' => $message,
                'result' => $data,
            ]
        );
    }

    /**
     * @param string $originalFileName
     */
    private function parseCsvFile($originalFileName)
    {
        $types = ['mobile', 'be', 'pm', 'qa', 'design'];

        $result = [];
        if (false !== ($handle = \fopen($originalFileName, 'r'))) {
            \fgetcsv($handle, 1000);
            \fgetcsv($handle, 1000);
            $row = 0;
            while (false !== ($data = \fgetcsv($handle, 1000))) {
                $androidPrice = \array_slice($data, 3, 5);
                $iosPrice = \array_slice($data, 8, 5);
                $androidIosPrice = \array_slice($data, 13, 5);
                $androidPrice = $this->prepareArray($types, $androidPrice);
                $iosPrice = $this->prepareArray($types, $iosPrice);
                $androidIosPrice = $this->prepareArray($types, $androidIosPrice);

                $result[$row]['name'] = \strtolower(\preg_replace('~([\s\-/])~', '_', $data[0]));
                $result[$row]['title'] = $data[0];
                $result[$row]['description']['en'] = $data[1];
                $result[$row]['description']['ru'] = $data[2];
                $result[$row]['price']['android'] = $androidPrice;
                $result[$row]['price']['ios'] = $iosPrice;
                $result[$row]['price']['android_ios'] = $androidIosPrice;
                ++$row;
            }
            \fclose($handle);
        }

        return $result;
    }

    /**
     * @param array $types
     * @param array $data
     *
     * @return array
     */
    private function prepareArray(array $types, array $data)
    {
        $result = [];
        $i = 0;
        foreach ($types as $type) {
            if (!isset($data[$i])) {
                break;
            }
            $result[$type] = $data[$i];
            ++$i;
        }

        return $result;
    }
}
