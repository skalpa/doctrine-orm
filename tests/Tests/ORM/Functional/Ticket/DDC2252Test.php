<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\HasLifecycleCallbacks;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InverseJoinColumn;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinTable;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Doctrine\Tests\OrmFunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('DDC-2252')]
class DDC2252Test extends OrmFunctionalTestCase
{
    /** @phpstan-var DDC2252User */
    private $user;

    /** @phpstan-var DDC2252MerchantAccount */
    private $merchant;

    /** @phpstan-var DDC2252Membership */
    private $membership;

    /** @phpstan-var list<DDC2252Privilege> */
    private array $privileges = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchemaForModels(
            DDC2252User::class,
            DDC2252Privilege::class,
            DDC2252Membership::class,
            DDC2252MerchantAccount::class,
        );

        $this->loadFixtures();
    }

    public function loadFixtures(): void
    {
        $this->user       = new DDC2252User();
        $this->merchant   = new DDC2252MerchantAccount();
        $this->membership = new DDC2252Membership($this->user, $this->merchant);

        $this->privileges[] = new DDC2252Privilege();
        $this->privileges[] = new DDC2252Privilege();
        $this->privileges[] = new DDC2252Privilege();

        $this->membership->addPrivilege($this->privileges[0]);
        $this->membership->addPrivilege($this->privileges[1]);
        $this->membership->addPrivilege($this->privileges[2]);

        $this->_em->persist($this->user);
        $this->_em->persist($this->merchant);
        $this->_em->persist($this->privileges[0]);
        $this->_em->persist($this->privileges[1]);
        $this->_em->persist($this->privileges[2]);
        $this->_em->flush();

        $this->_em->persist($this->membership);
        $this->_em->flush();
        $this->_em->clear();
    }

    public function testIssue(): void
    {
        $identifier = [
            'merchantAccount' => $this->merchant->getAccountid(),
            'userAccount'     => $this->user->getUid(),
        ];

        $membership = $this->_em->find(DDC2252Membership::class, $identifier);

        self::assertInstanceOf(DDC2252Membership::class, $membership);
        self::assertCount(3, $membership->getPrivileges());

        $membership->getPrivileges()->remove(2);
        $this->_em->persist($membership);
        $this->_em->flush();
        $this->_em->clear();

        $membership = $this->_em->find(DDC2252Membership::class, $identifier);

        self::assertInstanceOf(DDC2252Membership::class, $membership);
        self::assertCount(2, $membership->getPrivileges());

        $membership->getPrivileges()->clear();
        $this->_em->persist($membership);
        $this->_em->flush();
        $this->_em->clear();

        $membership = $this->_em->find(DDC2252Membership::class, $identifier);

        self::assertInstanceOf(DDC2252Membership::class, $membership);
        self::assertCount(0, $membership->getPrivileges());

        $membership->addPrivilege($privilege3 = new DDC2252Privilege());
        $this->_em->persist($privilege3);
        $this->_em->persist($membership);
        $this->_em->flush();
        $this->_em->clear();

        $membership = $this->_em->find(DDC2252Membership::class, $identifier);

        self::assertInstanceOf(DDC2252Membership::class, $membership);
        self::assertCount(1, $membership->getPrivileges());
    }
}

#[Table(name: 'ddc2252_acl_privilege')]
#[Entity]
class DDC2252Privilege
{
    /** @var int */
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer')]
    protected $privilegeid;

    public function getPrivilegeid(): int
    {
        return $this->privilegeid;
    }
}

#[Table(name: 'ddc2252_mch_account')]
#[Entity]
class DDC2252MerchantAccount
{
    /** @var int */
    #[Id]
    #[Column(type: 'integer')]
    protected $accountid = 111;

    public function getAccountid(): int
    {
        return $this->accountid;
    }
}

#[Table(name: 'ddc2252_user_account')]
#[Entity]
class DDC2252User
{
    /** @var int */
    #[Id]
    #[Column(type: 'integer')]
    protected $uid = 222;

    /** @phpstan-var Collection<int, DDC2252Membership> */
    #[OneToMany(targetEntity: 'DDC2252Membership', mappedBy: 'userAccount', cascade: ['persist'])]
    #[JoinColumn(name: 'uid', referencedColumnName: 'uid')]
    protected $memberships;

    public function __construct()
    {
        $this->memberships = new ArrayCollection();
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    /** @phpstan-return Collection<int, DDC2252Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(DDC2252Membership $membership): void
    {
        $this->memberships[] = $membership;
    }
}

#[Table(name: 'ddc2252_mch_account_member')]
#[Entity]
#[HasLifecycleCallbacks]
class DDC2252Membership
{
    /** @phpstan-var Collection<int, DDC2252Privilege> */
    #[JoinTable(name: 'ddc2252_user_mch_account_privilege')]
    #[JoinColumn(name: 'mch_accountid', referencedColumnName: 'mch_accountid')]
    #[JoinColumn(name: 'uid', referencedColumnName: 'uid')]
    #[InverseJoinColumn(name: 'privilegeid', referencedColumnName: 'privilegeid')]
    #[ManyToMany(targetEntity: 'DDC2252Privilege', indexBy: 'privilegeid')]
    protected $privileges;

    public function __construct(
        #[Id]
        #[ManyToOne(targetEntity: 'DDC2252User', inversedBy: 'memberships')]
        #[JoinColumn(name: 'uid', referencedColumnName: 'uid')]
        protected DDC2252User $userAccount,
        #[Id]
        #[ManyToOne(targetEntity: 'DDC2252MerchantAccount')]
        #[JoinColumn(name: 'mch_accountid', referencedColumnName: 'accountid')]
        protected DDC2252MerchantAccount $merchantAccount,
    ) {
        $this->privileges = new ArrayCollection();
    }

    public function addPrivilege(DDC2252Privilege $privilege): void
    {
        $this->privileges[] = $privilege;
    }

    /** @phpstan-var Collection<int, DDC2252Privilege> */
    public function getPrivileges(): Collection
    {
        return $this->privileges;
    }
}
