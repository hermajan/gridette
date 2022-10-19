<?php

namespace Gridette;

use Nette\Database\{Connection, ResultSet};
use Nette\Localization\ITranslator;
use Ublaboo\DataGrid\DataGrid;

class DatagridFactory {
	/** @var array */
	private $config;
	
	/** @var Connection */
	private $connection;
	
	/** @var ITranslator */
	private $translator;
	
	/** @var DataGrid */
	private $grid;
	
	public function __construct(Connection $connection, ITranslator $translator) {
		$this->connection = $connection;
		$this->translator = $translator;
		
		$this->grid = new DataGrid();
	}
	
	public function create(array $config = []): DataGrid {
		$this->config = $config;
		
		/** @var ResultSet $resultSet */
		$resultSet = $this->connection->query("SELECT * FROM ?name", $this->config["table"]);
		
		$this->grid->setDataSource($resultSet->fetchAll())
			->setItemsPerPageList([10, 50, 100], true)
			->setDefaultPerPage(50)
			->setTranslator($this->translator)
			->addExportCsvFiltered("Export CSV", $this->config["table"].".csv", "windows-1250");
		
		$this->generateGrid($resultSet);
		
		if(isset($this->config["order"])) {
			$this->grid->setDefaultSort($this->config["order"]);
		}
		
		$this->grid->setStrictSessionFilterValues(false)->setColumnsHideable();
		
		return $this->grid;
	}
	
	public function generateGrid(ResultSet $resultSet): void {
		$fields = $resultSet->getColumnTypes();
		
		foreach($fields as $key => $type) {
			if(isset($this->config["hide"]) and in_array($key, $this->config["hide"])) {
				continue;
			}
			
			if(isset($this->config["columns"]) and array_key_exists($key, $this->config["columns"]) and is_string($key)) {
				$column = $this->config["columns"][$key];
			} else {
				$column = $key;
			}
			
			if(isset($this->config["types"]) and array_key_exists($key, $this->config["types"]) and is_string($key)) {
				$type = $this->config["types"][$key];
			}

//			Debugger::barDump([$key, $column, $type]);
			switch($type) {
				case "int":
				case "integer":
					$this->grid->addColumnNumber($key, $column)->setFormat(0, ",", "")
						//->setEditableCallback(function($id, $value) use ($key) {
						//	$this->updateValue($id, $value, $key);
						//})
						->setSortable()->setFilterText();
					break;
				
				case "text":
				case "string":
					$this->grid->addColumnText($key, $column)
						//->setEditableCallback(function($id, $value) use ($key) {
						//	$this->updateValue($id, $value, $key);
						//})
						->setSortable()->setFilterText();
					break;
				
				case "date":
					$this->grid->addColumnDateTime($key, $column)
						->setFormat("d.m.Y")
						->setSortable()
						->setFilterDateRange();
					break;
				case "datetime":
					$this->grid->addColumnDateTime($key, $column)
						->setFormat("H:i:s d.m.Y")
						->setSortable()
						->setFilterDateRange();
					break;
				
				case "boolean":
					$this->grid->addColumnStatus($key, $column)
						->addOption(1, "Ano")
						->setIcon("check")
						->setClass("btn-success")
						->endOption()
						->addOption(0, "Ne")
						->setIcon("close")
						->setClass("btn-danger")
						->endOption()->onChange[] = function($id, $value) use ($key) {
						$this->updateBoolean($id, $key, $value);
					};
					break;
				
				default:
					$this->grid->addColumnText($key, $column);
					break;
			}
		}
	}
	
	public function updateBoolean(string $id, string $key, string $value): void {
		$primaryKey = null;
		
		$indexes = $this->connection->getDriver()->getIndexes($this->config["table"]);
		foreach($indexes as $index) {
			if($index["primary"] === true) {
				$primaryKey = $index["columns"][0];
			}
		}
		
		if(isset($primaryKey)) {
			$this->connection->query("UPDATE ?name", $this->config["table"], "SET", [$key => $value], "WHERE ?name", $primaryKey, " = ?", $id);
			$this->grid->reload();
		}
	}
}
