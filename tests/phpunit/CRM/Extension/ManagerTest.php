<?php
/*
 +--------------------------------------------------------------------+
| CiviCRM version 4.6                                                |
+--------------------------------------------------------------------+
| Copyright CiviCRM LLC (c) 2004-2015                                |
+--------------------------------------------------------------------+
| This file is a part of CiviCRM.                                    |
|                                                                    |
| CiviCRM is free software; you can copy, modify, and distribute it  |
| under the terms of the GNU Affero General Public License           |
| Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
|                                                                    |
| CiviCRM is distributed in the hope that it will be useful, but     |
| WITHOUT ANY WARRANTY; without even the implied warranty of         |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
| See the GNU Affero General Public License for more details.        |
|                                                                    |
| You should have received a copy of the GNU Affero General Public   |
| License and the CiviCRM Licensing Exception along                  |
| with this program; if not, contact CiviCRM LLC                     |
| at info[AT]civicrm[DOT]org. If you have questions about the        |
| GNU Affero General Public License or the licensing of CiviCRM,     |
| see the CiviCRM license FAQ at http://civicrm.org/licensing        |
+--------------------------------------------------------------------+
 */
require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Class CRM_Extension_ManagerTest
 */
class CRM_Extension_ManagerTest extends CiviUnitTestCase {
  const TESTING_TYPE = 'report';
  const OTHER_TESTING_TYPE = 'module';

  public function setUp() {
    parent::setUp();
    list ($this->basedir, $this->container) = $this->_createContainer();
    $this->mapper = new CRM_Extension_Mapper($this->container);
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Install an extension with an invalid type name.
   *
   * @expectedException CRM_Extension_Exception
   */
  public function testInstallInvalidType() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $testingTypeManager->expects($this->never())
      ->method('onPreInstall');
    $manager = $this->_createManager(array(
      self::OTHER_TESTING_TYPE => $testingTypeManager,
    ));
    $manager->install(array('test.foo.bar'));
  }

  /**
   * Install an extension with a valid type name.
   *
   * Note: We initially install two extensions but then toggle only
   * the second. This controls for bad SQL queries which hit either
   * "the first row" or "all rows".
   */
  public function testInstall_Disable_Uninstall() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('uninstalled', $manager->getStatus('test.foo.bar'));
    $this->assertEquals('uninstalled', $manager->getStatus('test.whiz.bang'));

    $testingTypeManager
      ->expects($this->exactly(2))
      ->method('onPreInstall');
    $testingTypeManager
      ->expects($this->exactly(2))
      ->method('onPostInstall');
    $manager->install(array('test.whiz.bang', 'test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreDisable');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostDisable');
    $manager->disable(array('test.foo.bar'));
    $this->assertEquals('disabled', $manager->getStatus('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang')); // no side-effect

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreUninstall');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostUninstall');
    $manager->uninstall(array('test.foo.bar'));
    $this->assertEquals('uninstalled', $manager->getStatus('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang')); // no side-effect
  }

  /**
   * Install an extension and then harshly remove the underlying source.
   * Subseuently disable and uninstall.
   */
  public function testInstall_DirtyRemove_Disable_Uninstall() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('uninstalled', $manager->getStatus('test.foo.bar'));

    $manager->install(array('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.foo.bar'));

    $this->assertTrue(file_exists("{$this->basedir}/weird/foobar/info.xml"));
    CRM_Utils_File::cleanDir("{$this->basedir}/weird/foobar", TRUE, FALSE);
    $this->assertFalse(file_exists("{$this->basedir}/weird/foobar/info.xml"));
    $manager->refresh();
    $this->assertEquals('installed-missing', $manager->getStatus('test.foo.bar'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreDisable');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostDisable');
    $manager->disable(array('test.foo.bar'));
    $this->assertEquals('disabled-missing', $manager->getStatus('test.foo.bar'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreUninstall');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostUninstall');
    $manager->uninstall(array('test.foo.bar'));
    $this->assertEquals('unknown', $manager->getStatus('test.foo.bar'));
  }

  /**
   * Install an extension with a valid type name.
   */
  public function testInstall_Disable_Enable() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('uninstalled', $manager->getStatus('test.foo.bar'));
    $this->assertEquals('uninstalled', $manager->getStatus('test.whiz.bang'));

    $testingTypeManager
      ->expects($this->exactly(2))
      ->method('onPreInstall');
    $testingTypeManager
      ->expects($this->exactly(2))
      ->method('onPostInstall');
    $manager->install(array('test.whiz.bang', 'test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreDisable');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostDisable');
    $manager->disable(array('test.foo.bar'));
    $this->assertEquals('disabled', $manager->getStatus('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreEnable');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostEnable');
    $manager->enable(array('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang'));
  }

  /**
   * Performing 'install' on a 'disabled' extension performs an 'enable'
   */
  public function testInstall_Disable_Install() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('uninstalled', $manager->getStatus('test.foo.bar'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreInstall');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostInstall');
    $manager->install(array('test.foo.bar'));
    $this->assertEquals('installed', $manager->getStatus('test.foo.bar'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreDisable');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostDisable');
    $manager->disable(array('test.foo.bar'));
    $this->assertEquals('disabled', $manager->getStatus('test.foo.bar'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreEnable');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostEnable');
    $manager->install(array('test.foo.bar')); // install() instead of enable()
    $this->assertEquals('installed', $manager->getStatus('test.foo.bar'));
  }

  /**
   * Install an extension with a valid type name.
   */
  public function testEnableBare() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('uninstalled', $manager->getStatus('test.foo.bar'));

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreInstall');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostInstall');
    $testingTypeManager
      ->expects($this->never())
      ->method('onPreEnable');
    $testingTypeManager
      ->expects($this->never())
      ->method('onPostEnable');
    $manager->enable(array('test.foo.bar')); // enable not install
    $this->assertEquals('installed', $manager->getStatus('test.foo.bar'));
  }

  /**
   * Get the status of an unknown extension.
   */
  public function testStatusUnknownKey() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $testingTypeManager->expects($this->never())
      ->method('onPreInstall');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('unknown', $manager->getStatus('test.foo.bar.whiz.bang'));
  }

  /**
   * Replace code for an extension that doesn't exist in the container
   */
  public function testReplace_Unknown() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('unknown', $manager->getStatus('test.newextension'));

    $this->download = $this->_createDownload('test.newextension', 'newextension');

    $testingTypeManager
      ->expects($this->never())// no data to replace
      ->method('onPreReplace');
    $testingTypeManager
      ->expects($this->never())// no data to replace
      ->method('onPostReplace');
    $manager->replace($this->download);
    $this->assertEquals('uninstalled', $manager->getStatus('test.newextension'));
    $this->assertTrue(file_exists("{$this->basedir}/test.newextension/info.xml"));
    $this->assertTrue(file_exists("{$this->basedir}/test.newextension/newextension.php"));
    $this->assertEquals(self::TESTING_TYPE, $this->mapper->keyToInfo('test.newextension')->type);
    $this->assertEquals('newextension', $this->mapper->keyToInfo('test.newextension')->file);
  }

  /**
   * Replace code for an extension that doesn't exist in the container
   */
  public function testReplace_Uninstalled() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('uninstalled', $manager->getStatus('test.whiz.bang'));
    $this->assertEquals('oddball', $this->mapper->keyToInfo('test.whiz.bang')->file);

    $this->download = $this->_createDownload('test.whiz.bang', 'newextension');

    $testingTypeManager
      ->expects($this->never())// no data to replace
      ->method('onPreReplace');
    $testingTypeManager
      ->expects($this->never())// no data to replace
      ->method('onPostReplace');
    $manager->replace($this->download);
    $this->assertEquals('uninstalled', $manager->getStatus('test.whiz.bang'));
    $this->assertTrue(file_exists("{$this->basedir}/weird/whizbang/info.xml"));
    $this->assertTrue(file_exists("{$this->basedir}/weird/whizbang/newextension.php"));
    $this->assertFalse(file_exists("{$this->basedir}/weird/whizbang/oddball.php"));
    $this->assertEquals(self::TESTING_TYPE, $this->mapper->keyToInfo('test.whiz.bang')->type);
    $this->assertEquals('newextension', $this->mapper->keyToInfo('test.whiz.bang')->file);
  }

  /**
   * Install a module and then replace it with new code.
   *
   * Note that some metadata changes between versions -- the original has
   * file="oddball", and the upgrade has file="newextension".
   */
  public function testReplace_Installed() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));
    $this->assertEquals('uninstalled', $manager->getStatus('test.whiz.bang'));
    $this->assertEquals('oddball', $this->mapper->keyToInfo('test.whiz.bang')->file);

    $manager->install(array('test.whiz.bang'));
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang'));
    $this->assertEquals('oddball', $this->mapper->keyToInfo('test.whiz.bang')->file);
    $this->assertDBQuery('oddball', 'SELECT file FROM civicrm_extension WHERE full_name ="test.whiz.bang"');

    $this->download = $this->_createDownload('test.whiz.bang', 'newextension');

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreReplace');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostReplace');
    $manager->replace($this->download);
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang'));
    $this->assertTrue(file_exists("{$this->basedir}/weird/whizbang/info.xml"));
    $this->assertTrue(file_exists("{$this->basedir}/weird/whizbang/newextension.php"));
    $this->assertFalse(file_exists("{$this->basedir}/weird/whizbang/oddball.php"));
    $this->assertEquals('newextension', $this->mapper->keyToInfo('test.whiz.bang')->file);
    $this->assertDBQuery('newextension', 'SELECT file FROM civicrm_extension WHERE full_name ="test.whiz.bang"');
  }

  /**
   * Install a module and then delete (leaving stale DB info); restore
   * the module by downloading new code.
   *
   * Note that some metadata changes between versions -- the original has
   * file="oddball", and the upgrade has file="newextension".
   */
  public function testReplace_InstalledMissing() {
    $testingTypeManager = $this->getMock('CRM_Extension_Manager_Interface');
    $manager = $this->_createManager(array(
      self::TESTING_TYPE => $testingTypeManager,
    ));

    // initial installation
    $this->assertEquals('uninstalled', $manager->getStatus('test.whiz.bang'));
    $manager->install(array('test.whiz.bang'));
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang'));

    // dirty remove
    $this->assertTrue(file_exists("{$this->basedir}/weird/whizbang/info.xml"));
    CRM_Utils_File::cleanDir("{$this->basedir}/weird/whizbang", TRUE, FALSE);
    $this->assertFalse(file_exists("{$this->basedir}/weird/whizbang/info.xml"));
    $manager->refresh();
    $this->assertEquals('installed-missing', $manager->getStatus('test.whiz.bang'));

    // download and reinstall
    $this->download = $this->_createDownload('test.whiz.bang', 'newextension');

    $testingTypeManager
      ->expects($this->once())
      ->method('onPreReplace');
    $testingTypeManager
      ->expects($this->once())
      ->method('onPostReplace');
    $manager->replace($this->download);
    $this->assertEquals('installed', $manager->getStatus('test.whiz.bang'));
    $this->assertTrue(file_exists("{$this->basedir}/test.whiz.bang/info.xml"));
    $this->assertTrue(file_exists("{$this->basedir}/test.whiz.bang/newextension.php"));
    $this->assertEquals('newextension', $this->mapper->keyToInfo('test.whiz.bang')->file);
    $this->assertDBQuery('newextension', 'SELECT file FROM civicrm_extension WHERE full_name ="test.whiz.bang"');
  }

  /**
   * @param $typeManagers
   *
   * @return CRM_Extension_Manager
   */
  public function _createManager($typeManagers) {
    //list ($basedir, $c) = $this->_createContainer();
    $mapper = new CRM_Extension_Mapper($this->container);
    return new CRM_Extension_Manager($this->container, $this->container, $this->mapper, $typeManagers);
  }

  /**
   * @param CRM_Utils_Cache_Interface $cache
   * @param null $cacheKey
   *
   * @return array
   */
  public function _createContainer(CRM_Utils_Cache_Interface $cache = NULL, $cacheKey = NULL) {
    $basedir = $this->createTempDir('ext-');
    mkdir("$basedir/weird");
    mkdir("$basedir/weird/foobar");
    file_put_contents("$basedir/weird/foobar/info.xml", "<extension key='test.foo.bar' type='" . self::TESTING_TYPE . "'><file>oddball</file></extension>");
    // not needed for now // file_put_contents("$basedir/weird/bar/oddball.php", "<?php\n");
    mkdir("$basedir/weird/whizbang");
    file_put_contents("$basedir/weird/whizbang/info.xml", "<extension key='test.whiz.bang' type='" . self::TESTING_TYPE . "'><file>oddball</file></extension>");
    // not needed for now // file_put_contents("$basedir/weird/whizbang/oddball.php", "<?php\n");
    $c = new CRM_Extension_Container_Basic($basedir, 'http://example/basedir', $cache, $cacheKey);
    return array($basedir, $c);
  }

  /**
   * @param $key
   * @param $file
   *
   * @return string
   */
  public function _createDownload($key, $file) {
    $basedir = $this->createTempDir('ext-dl-');
    file_put_contents("$basedir/info.xml", "<extension key='$key' type='" . self::TESTING_TYPE . "'><file>$file</file></extension>");
    file_put_contents("$basedir/$file.php", "<?php\n");
    return $basedir;
  }

}
