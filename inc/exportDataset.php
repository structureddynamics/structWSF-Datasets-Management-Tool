<?php

  use \StructuredDynamics\osf\php\api\ws\search\SearchQuery;
  use \StructuredDynamics\osf\php\api\ws\crud\read\CrudReadQuery;
  use \StructuredDynamics\osf\php\api\ws\revision\lister\RevisionListerQuery;
  use \StructuredDynamics\osf\php\api\ws\revision\read\RevisionReadQuery;
  
  use \StructuredDynamics\osf\framework\Namespaces;
  use \StructuredDynamics\osf\framework\Resultset;
  use \StructuredDynamics\osf\framework\Subject;

  /**
  * Export a Dataset from a OSF Web Services instance
  * 
  * @param mixed $uri URI of the dataset to export
  * @param mixed $file File where to export the dataset
  * @param mixed $mime Mime to use for the exported dataset file
  * @param mixed $exclude_reifications Exclude reifications statements from the exported dataset
  * 
  * @return Return FALSE if the dataset couldn't be exported. Return TRUE otherwise.
  */
  function exportDataset($uri, $credentials, $file, $mime, $exclude_reifications)
  {
    // Exporting a dataset means that we will export 2 different, but highly related things:
    //
    // (1) The dataset itself get exported, without or without reification statements
    // (2) The revisions graph of the dataset get exported
    
    
    // Get the number of records in that dataset
    $search = new SearchQuery($credentials['osf-web-services'], $credentials['application-id'], $credentials['api-key'], $credentials['user']);
    
    $search->includeAggregates()
           ->items(0)
           ->datasetFilter($uri)
           ->send();
           
    if($search->isSuccessful())
    {
      $resultset = $search->getResultset()->getResultset();
      
      $nbResults = 0;
      
      $slice = 25;
      
      foreach($resultset['unspecified'] as $aggr)
      {
        if($aggr['type'][0] == Namespaces::$aggr.'Aggregate' &&
           $aggr[Namespaces::$aggr.'property'][0]['uri'] == Namespaces::$void.'Dataset' && 
           $aggr[Namespaces::$aggr.'object'][0]['uri'] == $uri)
        {
          $nbResults = $aggr[Namespaces::$aggr.'count'][0]['value'];
          $prefixes = array();

          @unlink($file);
          @unlink(getRevisionsFilePath($file));
          
          for($i = 0; $i < $nbResults; $i += $slice)
          {
            cecho('Exporting records '.$i.' to '.($i + $slice)."\n", 'CYAN');

            $searchExport = new SearchQuery($credentials['osf-web-services'], $credentials['application-id'], $credentials['api-key'], $credentials['user']);
            
            $searchExport->excludeAggregates()
                         ->includeAttribute('uri')
                         ->items($slice)
                         ->page($i)
                         ->datasetFilter($uri)
                         ->send();     
                         
            $uris = array();
            $datasets = array();
                         
            if($searchExport->isSuccessful())
            {
              $resultsetExport = $searchExport->getResultset();
              
              foreach($resultsetExport->getSubjects() as $subject)
              {
                $uris[] = $subject->getUri();

                $d = $subject->getObjectPropertyValues(Namespaces::$dcterms.'isPartOf');
                
                $datasets[] = $d[0]['uri'];
              }
              
              // Get the full description of the records from the CRUD: Read endpoint
              $crudRead = new CrudReadQuery($credentials['osf-web-services'], $credentials['application-id'], $credentials['api-key'], $credentials['user']);
              
              if($exclude_reifications)
              {
                $crudRead->excludeReification();
              }
              else
              {
                $crudRead->includeReification();
              }
              
              $crudRead->dataset($datasets)
                       ->uri($uris)
                       ->excludeLinksback()
                       ->mime($mime)
                       ->send();
              
              if($crudRead->isSuccessful())
              {
                $rdf = $crudRead->getResultset();
                
                switch($mime)
                {
                  case 'application/rdf+n3':
                    $prefixes = array_merge(getN3Prefixes($rdf), $prefixes);
                    $rdf = n3RemovePrefixes($rdf);
                    $rdf = n3RemoveIsPartOf($rdf);
                    
                    file_put_contents($file, $rdf, FILE_APPEND);
                  break;      
                  case 'application/rdf+xml':
                      $prefixes = array_merge(getXMLPrefixes($rdf), $prefixes);
                      $rdf = xmlRemovePrefixes($rdf);
                      $rdf = xmlRemoveIsPartOf($rdf);
                      
                      file_put_contents($file, $rdf, FILE_APPEND);
                  break;            
                }              
              }
              else
              {
                cecho("Error exporting this dataset slice.\n", 'RED');  
              }
              
              foreach($uris as $key => $u)
              {
                $revisionLister = new RevisionListerQuery($credentials['osf-web-services'], $credentials['application-id'], $credentials['api-key'], $credentials['user']);
                
                $revisionLister->dataset($datasets[$key])
                               ->uri($u)
                               ->shortResults()
                               ->mime('resultset')
                               ->send();
                               
                if($revisionLister->isSuccessful())
                {
                  $listerResultset = $revisionLister->getResultset()->getResultset();
                  
                  if(!empty($listerResultset))
                  {
                    foreach($listerResultset['unspecified'] as $ruri => $revision)
                    {
                      $revisionRead = new RevisionReadQuery($credentials['osf-web-services'], $credentials['application-id'], $credentials['api-key'], $credentials['user']);
                      
                      $revisionRead->dataset($datasets[$key])
                                   ->revisionUri($ruri)
                                   ->getRevision()
                                   ->mime('resultset')
                                   ->send();
                                   
                      if($revisionRead->isSuccessful())
                      {
                        $revision = $revisionRead->getResultset();    
                        
                        $rset = $revision->getResultset();
                        
                        unset($rset[$datasets[$key]][$ruri]['http://purl.org/dc/terms/isPartOf']);
                        
                        $revision->setResultset($rset);
                        
                        switch($mime)
                        {
                          case 'application/rdf+n3':
                            $revisionRdf = $revision->getResultsetRDFN3();
                          
                            $prefixes = array_merge(getN3Prefixes($revisionRdf), $prefixes);
                            $revisionRdf = n3RemovePrefixes($revisionRdf);
                            
                            file_put_contents(getRevisionsFilePath($file), $revisionRdf, FILE_APPEND);
                          break;      
                          case 'application/rdf+xml':
                            $revisionRdf = $revision->getResultsetRDFXML();
                            
                            $prefixes = array_merge(getXMLPrefixes($revisionRdf), $prefixes);
                            $revisionRdf = xmlRemovePrefixes($revisionRdf);
                              
                            file_put_contents(getRevisionsFilePath($file), $revisionRdf, FILE_APPEND);
                          break;            
                        }                        
                      }
                      else
                      {
                        
                      }                                   
                    }
                  }
                  else
                  {
                    
                  }
                }
              }   
            } 
            else
            {
              cecho("Error exporting this dataset slice.\n", 'RED');  
            }     
          }
          
          switch($mime)
          {
            case 'application/rdf+n3':
            
              // Prepend the prefixes to the dataset file.
              $tmpFile = md5(microtime());
              $first = TRUE;
              foreach($prefixes as $prefix => $uri)
              {
                if($first)
                {
                  exec("echo '@prefix $prefix: <$uri> .' > /tmp/$tmpFile");
                  $first = FALSE;
                }
                else
                {
                  exec("echo '@prefix $prefix: <$uri> .' >> /tmp/$tmpFile");
                }
              }
              
              exec("echo '\n\n\n' >> /tmp/$tmpFile");
              
              exec("cat $file >> /tmp/$tmpFile");
              exec("cp /tmp/$tmpFile $file");
              
              unlink("/tmp/$tmpFile");
              
              if(file_exists(getRevisionsFilePath($file)))
              {
                $tmpFile = md5(microtime());
                $first = TRUE;
                foreach($prefixes as $prefix => $uri)
                {
                  if($first)
                  {
                    exec("echo '@prefix $prefix: <$uri> .' > /tmp/$tmpFile");
                    $first = FALSE;
                  }
                  else
                  {
                    exec("echo '@prefix $prefix: <$uri> .' >> /tmp/$tmpFile");
                  }
                }
                
                exec("echo '\n\n\n' >> /tmp/$tmpFile");
                
                exec("cat ".getRevisionsFilePath($file)." >> /tmp/$tmpFile");
                exec("cp /tmp/$tmpFile ".getRevisionsFilePath($file));
                
                unlink("/tmp/$tmpFile");
              }
              
            break;
            
            case 'application/rdf+xml':
              // Prepend the prefixes to the dataset file.
              $tmpFile = md5(microtime());

              exec("echo '<?xml version=\"1.0\"?>' > /tmp/$tmpFile");
              exec("echo '<rdf:RDF ' >> /tmp/$tmpFile");
              
              foreach($prefixes as $prefix => $uri)
              {
                exec("echo 'xmlns:$prefix=\"$uri\"' >> /tmp/$tmpFile");
              }

              exec("echo '>' >> /tmp/$tmpFile");

              
              exec("echo '\n\n\n' >> /tmp/$tmpFile");
              
              exec("cat ".getRevisionsFilePath($file)." >> /tmp/$tmpFile");
              exec("cp /tmp/$tmpFile ".getRevisionsFilePath($file));
              
              unlink("/tmp/$tmpFile");
              
              if(file_exists(getRevisionsFilePath($file)))
              {
                $tmpFile = md5(microtime());

                exec("echo '<?xml version=\"1.0\"?>' > /tmp/$tmpFile");
                exec("echo '<rdf:RDF ' >> /tmp/$tmpFile");
                
                foreach($prefixes as $prefix => $uri)
                {
                  exec("echo 'xmlns:$prefix=\"$uri\"' >> /tmp/$tmpFile");
                }

                exec("echo '>' >> /tmp/$tmpFile");

                
                exec("echo '\n\n\n' >> /tmp/$tmpFile");
                
                exec("cat ".getRevisionsFilePath($file)." >> /tmp/$tmpFile");
                exec("cp /tmp/$tmpFile ".getRevisionsFilePath($file));
                
                unlink("/tmp/$tmpFile");                
              }              
              
            break;            
          }          
        }
      }
      
    }
    else
    {
      $debugFile = md5(microtime()).'.error';
      file_put_contents('/tmp/'.$debugFile, var_export($search, TRUE));
           
      @cecho('Can\'t export dataset '.$uri.'. '. $search->getStatusMessage() . 
           $search->getStatusMessageDescription()."\nDebug file: /tmp/$debugFile\n", 'RED');
           
      return(FALSE);
    }

    return(TRUE);
  }

  function getN3Prefixes($rdf)
  {
    preg_match_all('/@prefix\s(.*):\s<(.*)>\s\./', $rdf, $matches);
    
    return(array_combine($matches[1], $matches[2]));
  }
  
  function getXMLPrefixes($rdf)
  {
    preg_match_all('/xmlns:(.*)="(.*)"/', $rdf, $matches);
    
    return(array_combine($matches[1], $matches[2]));
  }
  
  function n3RemovePrefixes($rdf)
  {
    return(preg_replace('/@prefix.*\./', '', $rdf));
  }  
  
  function xmlRemovePrefixes($rdf)
  {
    $pos = strpos($rdf, '<rdf:RDF');
    $posEnd = strpos($rdf, '>', $pos) + 1;
    
    $rdf = substr($rdf, $posEnd);
    
    $rdf = str_replace('</rdf:RDF>', '', $rdf);
    
    return($rdf);
  }
  
  function n3RemoveIsPartOf($rdf)
  {
    // Remove dcterms:isPartOf
    $rdf = preg_replace('/.*dcterms:isPartOf\s<.*/', '', $rdf);
    
    // Fix record ending serialization
    $rdf = str_replace(";\n\n", ".\n", $rdf);
    
    return($rdf);
  }
  
  function xmlRemoveIsPartOf($rdf)
  {
    return(preg_replace('/<dcterms:isPartOf\srdf:resource=".*"\s\/>/', '', $rdf));
  }  
  
  function getRevisionsFilePath($file)
  {
    $path = '';
    $filename = $file;
    
    $pos = strrpos($file, '/');
    
    if($pos !== FALSE)
    {
      $path = substr($file, 0, $pos + 1);
      $filename = substr($file, $pos + 1);
    }
    
    return($path.'revisions.'.$filename);
  }
?>
