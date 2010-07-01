  /**
   * Retrieves a  colections of <?php echo $this->getModelClass() ?> objects
   * @param   sfWebRequest   $request a request object
   * @return  string
   */
  public function executeIndex(sfWebRequest $request)
  {
    $this->forward404Unless($request->isMethod(sfRequest::GET));
    $params = $request->getParameterHolder()->getAll();

    // notify an event before the action's body starts
    $this->dispatcher->notify(new sfEvent($this, 'sfDoctrineRestGenerator.get.pre', array('params' => $params)));

    $format = isset($params['sf_format']) ? $params['sf_format'] : '<?php echo $this->configuration->getValue('get.default_format') ?>';
    $request->setRequestFormat('html');

    if (!in_array($format, <?php var_export($this->configuration->getValue('default.formats_enabled', array('json', 'xml'))) ?>))
    {
      $format = '<?php echo $this->configuration->getValue('get.default_format') ?>';
    }

    unset($params['sf_format']);
    unset($params['module']);
    unset($params['action']);

    $additional_params = <?php var_export($this->configuration->getValue('get.additional_params', array())); ?>;

    foreach ($params as $name => $value)
    {
      if (!$value || in_array($name, $additional_params))
      {
        unset($params[$name]);
      }
    }

    try
    {
      $this->validateIndex($params);
    }
    catch (Exception $e)
    {
    	$this->getResponse()->setStatusCode(406);
      $serializer = sfResourceSerializer::getInstance($format);
      $this->getResponse()->setContentType($serializer->getContentType());
      $error = $e->getMessage();

      // event filter to enable customisation of the error message.
      $result = $this->dispatcher->filter(
        new sfEvent($this, 'sfDoctrineRestGenerator.filter_error_output'),
        $error
      )->getReturnValue();

      if ($error === $result)
      {
        $error = array(array('message' => $error));
        $this->output = $serializer->serialize($error, 'error');
      }
      else
      {
        $this->output = $serializer->serialize($result);
      }

      return sfView::SUCCESS;
    }

    $this->query($params);
<?php $primaryKeys = $this->getPrimaryKeys(); ?>
<?php foreach ($primaryKeys as $primaryKey): ?>
    $isset_pk = (!isset($isset_pk) || $isset_pk) && isset($params['<?php echo $primaryKey ?>']);
<?php endforeach; ?>
    $this->forward404Unless(!$isset_pk || ($isset_pk && count($this->objects) > 0));

<?php $embed_relations = $this->configuration->getValue('get.embed_relations'); ?>
<?php foreach ($embed_relations as $embed_relation): ?>
<?php if ($this->isManyToManyRelation($embed_relation)): ?>
    $this->embedManyToMany<?php echo $embed_relation ?>($params);
<?php endif; ?><?php endforeach; ?>
<?php $object_additional_fields = $this->configuration->getValue('get.object_additional_fields'); ?>
<?php if (count($object_additional_fields) > 0): ?>

    foreach ($this->objects as $key => $object)
    {
<?php foreach ($object_additional_fields as $field): ?>
      $this->embedAdditional<?php echo $field ?>($key, $params);
<?php endforeach; ?>
    }
<?php endif; ?><?php $global_additional_fields = $this->configuration->getValue('get.global_additional_fields'); ?>
<?php foreach ($global_additional_fields as $field): ?>

    $this->embedGlobalAdditional<?php echo $field ?>($params);
<?php endforeach; ?>

<?php $display = $this->configuration->getValue('get.display'); ?>
<?php if (count($display) > 0): ?>
    $accepted_keys = <?php echo var_export($display, true); ?>;

    foreach ($this->objects as $i => $object)
    {
      foreach ($object as $key => $value)
      {
        if (!in_array($key, $accepted_keys))
        {
          unset($object[$key]);
        }
      }

      $this->objects[$i] = $object;
    }
<?php endif; ?>

<?php
$fields = $this->configuration->getValue('default.fields');
$specific_configuration_directives = false;

foreach ($fields as $field => $configuration)
{
  if (isset($configuration['date_format']) || isset($configuration['tag_name']))
  {
    $specific_configuration_directives = true;
    continue;
  }
}
?>
<?php if ($specific_configuration_directives): ?>
    foreach ($this->objects as $i => $object)
    {
<?php foreach ($fields as $field => $configuration): ?>
<?php if (isset($configuration['date_format']) || isset($configuration['tag_name'])): ?>
      if (isset($object['<?php echo $field ?>']))
      {
<?php if (isset($configuration['date_format'])): ?>
        $object['<?php echo $field ?>'] = date('<?php echo $configuration['date_format'] ?>', strtotime($object['<?php echo $field ?>']));
<?php endif; ?>
<?php if (isset($configuration['tag_name'])): ?>
        $object['<?php echo $configuration['tag_name'] ?>'] = $object['<?php echo $field ?>'];
        unset($object['<?php echo $field ?>']);
<?php endif; ?>
      }
<?php endif; ?>
<?php endforeach; ?>

      $this->objects[$i] = $object;
    }
<?php endif; ?>

    $serializer = sfResourceSerializer::getInstance($format);
    $this->getResponse()->setContentType($serializer->getContentType());
    $this->output = $serializer->serialize($this->objects, $this->model);
    unset($this->objects);
  }
